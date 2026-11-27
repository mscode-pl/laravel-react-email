<?php

namespace MsCodePL\LaravelReactEmail\Commands;

use Illuminate\Console\Command;
use MsCodePL\LaravelReactEmail\Services\ReactEmailBuilder;
use Throwable;

class BuildReactEmailsCommand extends Command
{
    protected $signature = 'react-email:build
                            {templates?* : Template names to build (defaults to all)}
                            {--force : Force rebuild all templates, ignoring the change manifest}
                            {--clean : Remove compiled files for templates whose source was deleted}';

    protected $description = 'Build React Email templates to Blade views';

    public function __construct(protected ReactEmailBuilder $builder)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Building React Email templates...');

        if ($this->option('clean')) {
            $this->handleClean();
        }

        $templates = $this->argument('templates') ?: $this->builder->templateNames();

        if (empty($templates)) {
            $this->info('The email templates directory is empty. Nothing to build.');

            return self::SUCCESS;
        }

        try {
            $results = $this->option('force')
                ? $this->builder->buildAllBatch($templates)
                : $this->builder->buildChangedBatch($templates);
        } catch (Throwable $e) {
            $results = ['built' => [], 'skipped' => [], 'errors' => [$e->getMessage()]];
        }

        $this->displayResults($results);

        return empty($results['errors']) ? self::SUCCESS : self::FAILURE;
    }

    protected function handleClean(): void
    {
        $removed = $this->builder->cleanOrphaned();

        if (empty($removed)) {
            $this->line('No orphaned templates found.');

            return;
        }

        $this->info('Removed compiled files for deleted templates:');

        foreach ($removed as $name) {
            $this->line("  - $name");
        }
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
            $this->newLine();
            $this->error('Encountered errors while building some templates:');
            foreach ($results['errors'] as $error) {
                $this->error("  - $error");
            }
        }
    }
}
