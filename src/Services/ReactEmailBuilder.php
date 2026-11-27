<?php

namespace MsCodePL\LaravelReactEmail\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

class ReactEmailBuilder
{
    /**
     * Template file extensions, in lookup order.
     */
    public const EXTENSIONS = ['tsx', 'jsx', 'ts', 'js'];

    protected string $rendererScriptPath;

    protected string $templatePath;

    protected string $outputPath;

    /**
     * Timeout in seconds for a single renderer process (one batch of templates).
     */
    protected int $timeout = 300;

    public function __construct()
    {
        $this->rendererScriptPath = dirname(__DIR__, 2).'/scripts/react-email-renderer.tsx';

        $this->templatePath = rtrim(config('react-email.path'), '/\\');
        $this->outputPath = rtrim(config('react-email.build_path'), '/\\');
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    /**
     * Returns the source file for a template name (e.g. "welcome-email"), or
     * null when no file with a supported extension exists.
     */
    public function resolveSourcePath(string $name): ?string
    {
        foreach (self::EXTENSIONS as $extension) {
            $path = "$this->templatePath/$name.$extension";

            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Names of all templates in the top level of the template directory.
     * Subdirectories are treated as shared code (layouts, components).
     *
     * @return string[]
     */
    public function templateNames(): array
    {
        if (! File::isDirectory($this->templatePath)) {
            return [];
        }

        $names = array_map(
            fn (SplFileInfo $file) => $file->getFilenameWithoutExtension(),
            array_filter(
                File::files($this->templatePath),
                fn (SplFileInfo $file) => in_array($file->getExtension(), self::EXTENSIONS, true),
            ),
        );

        return array_values(array_unique($names));
    }

    /**
     * Builds a single template. Convenience wrapper around buildTemplatesBatch().
     *
     * @return array{string, string} [htmlOutputPath, plainTextOutputPath]
     */
    public function buildTemplate(string $filenameWithoutExtension): array
    {
        $results = $this->buildTemplatesBatch([$filenameWithoutExtension]);
        $result = $results[$filenameWithoutExtension] ?? null;

        if ($result === null || isset($result['error'])) {
            throw new RuntimeException($result['error'] ?? "No output for template: $filenameWithoutExtension");
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
     * @param  string[]  $filenamesWithoutExtension
     * @return array<string, array>
     */
    public function buildTemplatesBatch(array $filenamesWithoutExtension): array
    {
        if (empty($filenamesWithoutExtension)) {
            return [];
        }

        $this->ensureDirectoriesExist();

        $outputs = [];
        $sources = [];

        foreach ($filenamesWithoutExtension as $name) {
            $source = $this->resolveSourcePath($name);

            if ($source === null) {
                $outputs[$name] = ['error' => "Template not found: $this->templatePath/$name.tsx"];
            } else {
                $sources[$name] = $source;
            }
        }

        if (empty($sources)) {
            return $outputs;
        }

        $process = Process::timeout($this->timeout)
            ->path(base_path())
            ->run([...$this->getTsxCommand(), $this->rendererScriptPath, ...array_values($sources)]);

        if (! $process->successful()) {
            throw new RuntimeException("Renderer process failed\n".$process->errorOutput());
        }

        $rawResults = json_decode($process->output(), true);

        if (! is_array($rawResults)) {
            throw new RuntimeException(
                "Renderer returned invalid output\n".trim($process->output()."\n".$process->errorOutput())
            );
        }

        $names = array_flip($sources);

        foreach ($rawResults as $result) {
            $name = $names[$result['path']] ?? pathinfo($result['path'], PATHINFO_FILENAME);

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
     * @param  string[]  $names
     * @return array{built: array, skipped: array, errors: array}
     */
    public function buildAllBatch(array $names): array
    {
        return $this->buildBatch($names, force: true);
    }

    /**
     * Builds only the templates whose source hash differs from the manifest,
     * using a single batch Node.js process for all changed templates.
     *
     * @param  string[]  $names
     * @return array{built: array, skipped: array, errors: array}
     */
    public function buildChangedBatch(array $names): array
    {
        return $this->buildBatch($names, force: false);
    }

    /**
     * Builds a template only if the source file has changed since the last build.
     *
     * Returns the output paths on build, or false when skipped.
     *
     * @return array{string, string}|false
     */
    public function buildTemplateIfChanged(string $filenameWithoutExtension): array|false
    {
        $result = $this->buildBatch([$filenameWithoutExtension], force: false);

        if (! empty($result['errors'])) {
            throw new RuntimeException($result['errors'][0]);
        }

        return $result['built'][0] ?? false;
    }

    /**
     * Whether the compiled Blade view is missing or older than its source.
     */
    public function isStale(string $name): bool
    {
        $source = $this->resolveSourcePath($name);
        $compiled = "$this->outputPath/$name.blade.php";

        if ($source === null) {
            return false;
        }

        return ! File::exists($compiled) || File::lastModified($source) > File::lastModified($compiled);
    }

    /**
     * Removes built files for templates whose source no longer exists,
     * and cleans up their manifest entries.
     *
     * @return string[] List of removed template names.
     */
    public function cleanOrphaned(): array
    {
        $manifest = $this->readManifest();
        $removed = [];

        foreach (array_keys($manifest) as $name) {
            if ($this->resolveSourcePath($name) === null) {
                File::delete("$this->outputPath/$name.blade.php");
                File::delete("$this->outputPath/$name-text.blade.php");
                unset($manifest[$name]);
                $removed[] = $name;
            }
        }

        $this->writeManifest($manifest);

        return $removed;
    }

    /**
     * @param  string[]  $names
     * @return array{built: array, skipped: array, errors: array}
     */
    protected function buildBatch(array $names, bool $force): array
    {
        $manifest = $this->readManifest();
        $sharedHash = $this->sharedHash();
        $hashes = [];
        $skipped = [];
        $errors = [];

        foreach ($names as $name) {
            $source = $this->resolveSourcePath($name);

            if ($source === null) {
                $errors[] = "Template not found: $this->templatePath/$name.tsx";

                continue;
            }

            $hash = md5(md5_file($source).$sharedHash);

            if (! $force && ($manifest[$name]['hash'] ?? null) === $hash) {
                $skipped[] = $name;
            } else {
                $hashes[$name] = $hash;
            }
        }

        $built = [];

        if (! empty($hashes)) {
            foreach ($this->buildTemplatesBatch(array_keys($hashes)) as $name => $result) {
                if (isset($result['error'])) {
                    $errors[] = $result['error'];

                    continue;
                }

                $built[] = $result;
                $manifest[$name] = [
                    'hash' => $hashes[$name],
                    'built_at' => now()->toIso8601String(),
                ];
            }

            $this->writeManifest($manifest);
        }

        return ['built' => $built, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Hash of everything a template can depend on besides its own file:
     * shared code in template subdirectories and this package's renderer.
     * Changing any of it invalidates every template in the manifest.
     */
    protected function sharedHash(): string
    {
        $files = [];

        if (File::isDirectory($this->templatePath)) {
            foreach (File::allFiles($this->templatePath) as $file) {
                if ($file->getRelativePath() !== '' && ! str_contains($file->getRelativePath(), 'node_modules')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        foreach (File::files(dirname($this->rendererScriptPath)) as $file) {
            $files[] = $file->getPathname();
        }

        sort($files);

        return md5(implode('', array_map(fn (string $path) => $path.md5_file($path), $files)));
    }

    /**
     * Returns the command used to run tsx.
     *
     * Prefers a local node_modules installation (application first, then this
     * package), falling back to `npx --yes tsx` which downloads tsx if needed.
     *
     * @return string[]
     */
    protected function getTsxCommand(): array
    {
        $binary = PHP_OS_FAMILY === 'Windows' ? 'tsx.cmd' : 'tsx';

        foreach ([base_path('node_modules/.bin'), dirname(__DIR__, 2).'/node_modules/.bin'] as $directory) {
            if (File::exists("$directory/$binary")) {
                return ["$directory/$binary"];
            }
        }

        return ['npx', '--yes', 'tsx'];
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

        ksort($manifest);

        File::put($this->getManifestPath(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function ensureDirectoriesExist(): void
    {
        File::ensureDirectoryExists($this->templatePath);
        File::ensureDirectoryExists($this->outputPath);
    }
}
