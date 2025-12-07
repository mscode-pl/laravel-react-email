<?php

namespace MsCodePL\LaravelReactEmail\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class ReactEmailDevServerCommand extends Command
{
    protected $name = 'react-email:dev';

    protected $description = 'Run the React Email dev server';

    public function handle(): bool
    {
        $templatesPath = config('react-email.path', resource_path('react-email'));

        File::makeDirectory($templatesPath, 0755, true, true);

        $this->info('Starting React Email dev server...');
        $this->info("Templates directory: {$templatesPath}");

        $process = new Process(['npx', '--yes', 'react-email', 'dev', '--dir', $templatesPath, '--port', '3000']);
        $process->setTty(true);
        $process->setTimeout(null);

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return true;
    }
}

