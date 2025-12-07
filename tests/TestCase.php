<?php

use MsCodePL\LaravelReactEmail\LaravelReactEmailServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelReactEmailServiceProvider::class
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('laravel-react-email.template_dir', __DIR__ . '/emails/');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app->setBasePath(__DIR__ . '/..');
    }
}
