<?php

namespace MsCodePL\LaravelReactEmail\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ReactEmailBuilder
{
    protected string $rendererScriptPath;

    protected string $templatePath;

    protected string $outputPath;

    public function __construct()
    {
        $this->rendererScriptPath = __DIR__.'/../../scripts/react-email-renderer.tsx';

        $this->templatePath = config('react-email.path');
        $this->outputPath = config('react-email.build_path');
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    /**
     * Builds a single template. Convenience wrapper around buildTemplatesBatch().
     *
     * @return array{string, string} [htmlOutputPath, plainTextOutputPath]
     */
    public function buildTemplate(string $filenameWithoutExtension): array
    {
        $results = $this->buildTemplatesBatch([$filenameWithoutExtension]);
        $result  = $results[$filenameWithoutExtension] ?? null;

        if ($result === null || isset($result['error'])) {
            $msg = $result['error'] ?? "No output for template: $filenameWithoutExtension";
            throw new RuntimeException($msg);
        }

        return $result;
    }

    /**
     * Builds multiple templates in a SINGLE Node.js process invocation,
     * eliminating per-template startup overhead.
     *
     * Returns an array keyed by template name. Each value is either:
     *   - [htmlPath, plainTextPath]  on success
     *   - ['error' => 'message']     on per-template failure
     *
     * @param  string[] $filenamesWithoutExtension
     * @return array<string, array>
     */
    public function buildTemplatesBatch(array $filenamesWithoutExtension): array
    {
        if (empty($filenamesWithoutExtension)) {
            return [];
        }

        $this->ensureDirectoriesExist();

        $tsx         = $this->getTsxBinary();
        $rendererArg = escapeshellarg($this->rendererScriptPath);
        $templateArgs = implode(' ', array_map(
            fn ($name) => escapeshellarg("$this->templatePath/$name.tsx"),
            $filenamesWithoutExtension,
        ));

        $process = Process::run("$tsx $rendererArg $templateArgs");

        if (! $process->successful()) {
            throw new RuntimeException(
                "Renderer process failed\n".$process->errorOutput()
            );
        }

        $rawResults = json_decode($process->output(), true) ?? [];
        $outputs    = [];

        foreach ($rawResults as $result) {
            $name = pathinfo($result['path'], PATHINFO_FILENAME);

            if (! empty($result['error'])) {
                $outputs[$name] = ['error' => "Failed to build $name: {$result['error']}"];
                continue;
            }

            $htmlPath = "$this->outputPath/$name.blade.php";
            $textPath = "$this->outputPath/$name-text.blade.php";

            File::put($htmlPath, $result['html']);
            File::put($textPath, $result['plainText']);

            $outputs[$name] = [$htmlPath, $textPath];
        }

        return $outputs;
    }

    /**
     * Builds ALL given templates in one batch, updates the manifest, and
     * returns a result summary compatible with BuildReactEmailsCommand.
     *
     * @param  string[] $names
     * @return array{built: array, skipped: array, errors: array}
     */
    public function buildAllBatch(array $names): array
    {
        $results  = $this->buildTemplatesBatch($names);
        $manifest = $this->readManifest();
        $built    = [];
        $errors   = [];

        foreach ($results as $name => $result) {
            if (isset($result['error'])) {
                $errors[] = $result['error'];
            } else {
                $built[] = $result;
                $manifest[$name] = [
                    'hash'     => md5_file("$this->templatePath/$name.tsx"),
                    'built_at' => now()->toIso8601String(),
                ];
            }
        }

        $this->writeManifest($manifest);

        return ['built' => $built, 'skipped' => [], 'errors' => $errors];
    }

    /**
     * Builds only the templates whose source hash differs from the manifest,
     * using a single batch Node.js process for all changed templates.
     *
     * @param  string[] $names
     * @return array{built: array, skipped: array, errors: array}
     */
    public function buildChangedBatch(array $names): array
    {
        $manifest = $this->readManifest();
        $toBuild  = [];
        $skipped  = [];

        foreach ($names as $name) {
            $tsxPath = "$this->templatePath/$name.tsx";
            $hash    = md5_file($tsxPath);

            if (isset($manifest[$name]) && $manifest[$name]['hash'] === $hash) {
                $skipped[] = $name;
            } else {
                $toBuild[$name] = $hash;
            }
        }

        $built  = [];
        $errors = [];

        if (! empty($toBuild)) {
            $results = $this->buildTemplatesBatch(array_keys($toBuild));

            foreach ($results as $name => $result) {
                if (isset($result['error'])) {
                    $errors[] = $result['error'];
                } else {
                    $built[] = $result;
                    $manifest[$name] = [
                        'hash'     => $toBuild[$name],
                        'built_at' => now()->toIso8601String(),
                    ];
                }
            }

            $this->writeManifest($manifest);
        }

        return ['built' => $built, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Returns the tsx binary path for the current environment.
     *
     * Prefers the local node_modules installation (no npx resolution overhead).
     * Falls back to `npx --yes tsx` which downloads tsx if needed.
     */
    protected function getTsxBinary(): string
    {
        $ext      = PHP_OS_FAMILY === 'Windows' ? '.cmd' : '';
        $localBin = base_path("node_modules/.bin/tsx{$ext}");

        if (File::exists($localBin)) {
            return escapeshellarg($localBin);
        }

        return 'npx --yes tsx';
    }

    /**
     * Builds a template only if the source file has changed since the last build.
     *
     * Uses a content hash stored in `.react-email-manifest.json` inside the
     * build directory. Returns the output paths on build, or false when skipped.
     *
     * @return array{string, string}|false
     */
    public function buildTemplateIfChanged(string $filenameWithoutExtension): array|false
    {
        $templateFilePath = "$this->templatePath/$filenameWithoutExtension.tsx";
        $manifest         = $this->readManifest();
        $hash             = md5_file($templateFilePath);

        if (
            isset($manifest[$filenameWithoutExtension]) &&
            $manifest[$filenameWithoutExtension]['hash'] === $hash
        ) {
            return false; // unchanged, skip
        }

        $result = $this->buildTemplate($filenameWithoutExtension);

        $manifest[$filenameWithoutExtension] = [
            'hash'     => $hash,
            'built_at' => now()->toIso8601String(),
        ];
        $this->writeManifest($manifest);

        return $result;
    }

    /**
     * Removes built files for templates whose TSX source no longer exists,
     * and cleans up their manifest entries.
     *
     * @return string[] List of removed template names.
     */
    public function cleanOrphaned(): array
    {
        $manifest = $this->readManifest();
        $removed  = [];

        foreach ($manifest as $name => $_data) {
            $tsxPath = "$this->templatePath/$name.tsx";
            if (! File::exists($tsxPath)) {
                File::delete("$this->outputPath/$name.blade.php");
                File::delete("$this->outputPath/$name-text.blade.php");
                unset($manifest[$name]);
                $removed[] = $name;
            }
        }

        $this->writeManifest($manifest);

        return $removed;
    }

    // ---------------------------------------------------------------------------
    // Manifest helpers
    // ---------------------------------------------------------------------------

    protected function getManifestPath(): string
    {
        return "$this->outputPath/.react-email-manifest.json";
    }

    protected function readManifest(): array
    {
        $path = $this->getManifestPath();

        if (! File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?: [];
    }

    protected function writeManifest(array $manifest): void
    {
        $this->ensureDirectoriesExist();
        File::put($this->getManifestPath(), json_encode($manifest, JSON_PRETTY_PRINT));
    }

    private function ensureDirectoriesExist(): void
    {
        // Ensure the template directory exists
        File::makeDirectory($this->templatePath, 0755, true, true);

        // Ensure the output directory exists
        File::makeDirectory($this->outputPath, 0755, true, true);
    }
}

