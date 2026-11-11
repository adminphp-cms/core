<?php

namespace Gmarineau\AdminPhp;

use Gmarineau\AdminPhp\Console\InstallCommand;
use Gmarineau\AdminPhp\Console\UserCommand;
use Gmarineau\AdminPhp\Enums\PublicationStatus;
use Gmarineau\AdminPhp\Models\Block;
use Gmarineau\AdminPhp\Models\User;
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

        $this->registerGuard();
    }

    public function boot(): void
    {
        Relation::morphMap(['adminphp_block' => Block::class]);

        $this->registerBlueprintMacros();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'adminphp');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, UserCommand::class]);

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
     * Adds the admin session guard and its user provider to the auth config,
     * unless the application defines them itself.
     */
    protected function registerGuard(): void
    {
        $config = $this->app['config'];
        $guard = $config->get('adminphp.guard', 'adminphp');

        if (! $config->has('auth.providers.adminphp_users')) {
            $config->set('auth.providers.adminphp_users', ['driver' => 'eloquent', 'model' => User::class]);
        }

        if (! $config->has("auth.guards.{$guard}")) {
            $config->set("auth.guards.{$guard}", ['driver' => 'session', 'provider' => 'adminphp_users']);
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

            // Named explicitly so long table names stay under MySQL's 64 character limit.
            $this->index(['status', 'publish_start_at', 'publish_end_at'], "{$this->getTable()}_publication_index");
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
            'middleware' => config('adminphp.middleware', ['web']),
            'as' => 'adminphp.',
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/admin.php'));
    }
}
