<?php

namespace MsCodePL\LaravelReactEmail\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use MsCodePL\LaravelReactEmail\Services\ReactEmailBuilder;

class BuildReactEmailsCommand extends Command
{
    protected $signature = 'react-email:build
                            {--force : Force rebuild all templates, ignoring the change manifest}
                            {--clean : Remove compiled files for templates whose TSX source was deleted}';

    protected $description = 'Build React Email templates to Blade views';

    protected ReactEmailBuilder $builder;

    public function __construct(ReactEmailBuilder $builder)
    {
        parent::__construct();
        $this->builder = $builder;
    }

    public function handle(): void
    {
        $this->info('Building React Email templates...');

        if ($this->option('clean')) {
            $this->handleClean();
        }

        $templates = $this->getTemplates();

        if (empty($templates)) {
            $this->info('The email templates directory is empty. Nothing to build.');

            return;
        }

        $results = $this->buildTemplates($templates);

        $this->displayResults($results);
    }

    protected function handleClean(): void
    {
        $removed = $this->builder->cleanOrphaned();

        if (empty($removed)) {
            $this->line('No orphaned templates found.');
        } else {
            $this->info('Removed compiled files for deleted templates:');
            foreach ($removed as $name) {
                $this->line("  - $name");
            }
        }
    }

    protected function getTemplates(): array
    {
        $templatePath = $this->builder->getTemplatePath();

        File::makeDirectory($templatePath, 0755, true, true);

        return File::files($templatePath);
    }

    protected function buildTemplates(array $templates): array
    {
        $names = array_map(
            fn ($t) => $t->getFilenameWithoutExtension(),
            array_filter($templates, fn ($t) => $this->isValidTemplate($t)),
        );

        if (empty($names)) {
            return ['built' => [], 'skipped' => [], 'errors' => []];
        }

        try {
            return $this->option('force')
                ? $this->builder->buildAllBatch($names)
                : $this->builder->buildChangedBatch($names);
        } catch (Exception $e) {
            return ['built' => [], 'skipped' => [], 'errors' => [$e->getMessage()]];
        }
    }

    protected function isValidTemplate($template): bool
    {
        return in_array($template->getExtension(), ['tsx', 'ts', 'jsx', 'js']);
    }

    protected function displayResults(array $results): void
    {
        if (! empty($results['built'])) {
            $this->info('Successfully built templates:');
            foreach ($results['built'] as $file) {
                $this->line("  - $file[0]");
            }
        }

        if (! empty($results['skipped'])) {
            $this->line('Skipped (unchanged):');
            foreach ($results['skipped'] as $name) {
                $this->line("  - $name");
            }
        }

        if (! empty($results['errors'])) {
            $this->error("\nEncountered errors while building some templates:");
            foreach ($results['errors'] as $error) {
                $this->error("  - $error");
            }
        }
    }
}

