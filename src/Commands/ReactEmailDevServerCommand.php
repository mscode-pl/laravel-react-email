<?php

namespace MsCodePL\LaravelReactEmail\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class ReactEmailDevServerCommand extends Command
{
    protected $signature = 'react-email:dev
                            {--port=3000 : Port for the preview server}';

    protected $description = 'Run the React Email dev server';

    public function handle(): int
    {
        $templatesPath = config('react-email.path', resource_path('react-email'));

        File::ensureDirectoryExists($templatesPath);

        $this->info('Starting React Email dev server...');
        $this->info("Templates directory: {$templatesPath}");

        $process = new Process(
            [...$this->reactEmailCommand(), 'dev', '--dir', $templatesPath, '--port', (string) $this->option('port')],
            base_path(),
        );
        $process->setTimeout(null);

        // TTY is unavailable on Windows; stream the output instead.
        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
    }

    /**
     * @return string[]
     */
    protected function reactEmailCommand(): array
    {
        $binary = base_path('node_modules/.bin/'.(PHP_OS_FAMILY === 'Windows' ? 'email.cmd' : 'email'));

        return File::exists($binary) ? [$binary] : ['npx', '--yes', 'react-email'];
    }
}
