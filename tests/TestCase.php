<?php

use MsCodePL\LaravelReactEmail\LaravelReactEmailServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelReactEmailServiceProvider::class,
        ];
    }

    /**
     * Templates live in <package>/resources/react-email so that relative imports
     * like '../../scripts/index' inside test templates resolve to this package.
     */
    protected function defineEnvironment($app): void
    {
        $resources = dirname(__DIR__).'/resources';

        $app['config']->set('react-email.path', $resources.'/react-email');
        $app['config']->set('react-email.build_path', $resources.'/views/react-email');
        $app['config']->set('view.paths', [$resources.'/views']);
    }
}
