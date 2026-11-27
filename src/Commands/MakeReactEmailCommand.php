<?php

namespace MsCodePL\LaravelReactEmail\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class MakeReactEmailCommand extends GeneratorCommand
{
    protected $name = 'make:react-email';

    protected $description = 'Create a new ReactMailable class and React Email template';

    protected $type = 'React Email';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/ReactMailable.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Mail';
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the mailable class'],
        ];
    }

    public function handle(): int
    {
        if (parent::handle() === false) {
            return self::FAILURE;
        }

        $this->createReactEmailTemplate();

        return self::SUCCESS;
    }

    protected function createReactEmailTemplate(): void
    {
        $templatePath = $this->getTemplatePath();

        if (File::exists($templatePath)) {
            $this->components->warn(sprintf('React email template [%s] already exists, skipping.', $templatePath));

            return;
        }

        $this->ensureTemplateDirectoryExists();
        $this->createTemplateFromStub($templatePath);

        $this->components->info(sprintf('React email template [%s] created successfully.', $templatePath));
    }

    /**
     * Template file for the mailable. Uses the class basename so that
     * "Orders/ShippedEmail" maps to "shipped-email.tsx", the same view
     * ReactMailable resolves by default.
     */
    protected function getTemplatePath(): string
    {
        $templatesPath = config('react-email.path', resource_path('react-email'));

        return $templatesPath.'/'.Str::kebab($this->classBasename()).'.tsx';
    }

    protected function classBasename(): string
    {
        return class_basename(str_replace('/', '\\', trim($this->argument('name'))));
    }

    protected function ensureTemplateDirectoryExists(): void
    {
        File::ensureDirectoryExists(dirname($this->getTemplatePath()));
    }

    protected function createTemplateFromStub(string $templatePath): void
    {
        $stubPath = __DIR__.'/../../stubs/react-email-template.stub';
        $stub = File::get($stubPath);

        $name = Str::studly($this->classBasename());

        $content = str_replace(
            ['{{ functionName }}', '{{ name }}'],
            [Str::finish($name, 'Email'), $name],
            $stub
        );

        File::put($templatePath, $content);
    }
}

