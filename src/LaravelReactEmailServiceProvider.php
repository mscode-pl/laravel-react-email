<?php

namespace MsCodePL\LaravelReactEmail;

use MsCodePL\LaravelReactEmail\Commands\BuildReactEmailsCommand;
use MsCodePL\LaravelReactEmail\Commands\MakeReactEmailCommand;
use MsCodePL\LaravelReactEmail\Commands\ReactEmailDevServerCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelReactEmailServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-react-email')
            ->hasConfigFile('react-email')
            ->hasCommands([
                MakeReactEmailCommand::class,
                BuildReactEmailsCommand::class,
                ReactEmailDevServerCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->package->basePath('/../config/react-email.php') => config_path('react-email.php'),
            ], 'react-email-config');
        }
    }
}
