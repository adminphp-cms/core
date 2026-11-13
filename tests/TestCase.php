<?php

namespace AdminPhp\Tests;

use AdminPhp\AdminPhp;
use AdminPhp\AdminPhpServiceProvider;
use AdminPhp\Tests\Fixtures\ArticleModule;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        app(AdminPhp::class)->registerModules([ArticleModule::class]);
    }

    protected function getPackageProviders($app): array
    {
        return [AdminPhpServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
