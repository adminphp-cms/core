<?php

namespace Gmarineau\AdminPhp;

use Gmarineau\AdminPhp\Console\InstallCommand;
use Gmarineau\AdminPhp\Enums\PublicationStatus;
use Gmarineau\AdminPhp\Http\Middleware\Authorize;
use Gmarineau\AdminPhp\Models\Block;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminPhpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/adminphp.php', 'adminphp');

        $this->app->singleton(AdminPhp::class);
    }

    public function boot(): void
    {
        Relation::morphMap(['adminphp_block' => Block::class]);

        $this->registerBlueprintMacros();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'adminphp');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__.'/../config/adminphp.php' => config_path('adminphp.php'),
            ], 'adminphp-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'adminphp-migrations');

            $this->publishes([
                __DIR__.'/../public' => public_path('vendor/adminphp'),
            ], ['adminphp-assets', 'laravel-assets']);
        }
    }

    /**
     * `$table->publication()` adds the columns used by the HasPublication trait.
     */
    protected function registerBlueprintMacros(): void
    {
        Blueprint::macro('publication', function (): void {
            /** @var Blueprint $this */
            $this->string('status', 20)->default(PublicationStatus::Draft->value);
            $this->timestamp('published_at')->nullable();
            $this->timestamp('publish_start_at')->nullable();
            $this->timestamp('publish_end_at')->nullable();

            $this->index(['status', 'publish_start_at', 'publish_end_at']);
        });
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::group([
            'domain' => config('adminphp.domain'),
            'prefix' => config('adminphp.path'),
            'middleware' => [...config('adminphp.middleware', ['web']), Authorize::class],
            'as' => 'adminphp.',
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/admin.php'));
    }
}
