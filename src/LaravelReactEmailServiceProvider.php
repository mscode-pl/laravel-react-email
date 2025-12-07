<?php

namespace MsCodePL\LaravelReactEmail;

use Illuminate\Support\ServiceProvider;

class LaravelReactEmailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishConfiguration();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->getConfigPath(),
            'laravel-react-email'
        );
    }

    private function publishConfiguration(): void
    {
        $this->publishes([
            $this->getConfigPath() => config_path('laravel-react-email.php'),
        ]);
    }

    private function getConfigPath(): string
    {
        return dirname(__DIR__) . '/config/react-email.php';
    }
}
