<?php

namespace MsCodePL\LaravelReactEmail;

use MsCodePL\LaravelReactEmail\Exceptions\NodeNotFoundException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class Renderer
{
    private ExecutableFinder $finder;

    public function __construct()
    {
        $this->finder = app(ExecutableFinder::class);
    }

    public function compile(string $template, array $context = []): array
    {
        $process = $this->createProcess($template, $context);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return json_decode($process->getOutput(), true) ?? [];
    }

    private function createProcess(string $template, array $context): Process
    {
        return new Process(
            [
                $this->findNodeBinary(),
                $this->getTsxPath(),
                $this->normalizePath(dirname(__DIR__) . '/render.tsx'),
                $this->normalizePath($this->getTemplatePath($template)),
                json_encode($context),
            ],
            base_path()
        );
    }

    private function findNodeBinary(): string
    {
        $configured = config('laravel-react-email.node_path');

        return $configured
            ?? $this->finder->find('node')
            ?? throw new NodeNotFoundException('Node.js executable not found');
    }

    private function getTsxPath(): string
    {
        return config('laravel-react-email.tsx_path')
            ?? base_path('node_modules/tsx/dist/cli.mjs');
    }

    private function getTemplatePath(string $template): string
    {
        return config('laravel-react-email.template_dir') . $template;
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }


    public static function resolveNodeExecutable(): string
    {
        $finder = app(ExecutableFinder::class);
        $configured = config('laravel-react-email.node_path');

        return $configured
            ?? $finder->find('node')
            ?? throw new NodeNotFoundException('Node.js executable not found');
    }
}
