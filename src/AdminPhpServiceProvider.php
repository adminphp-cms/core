<?php

namespace Gmarineau\AdminPhp;

use Gmarineau\AdminPhp\Console\InstallCommand;
use Gmarineau\AdminPhp\Console\MakeBlockCommand;
use Gmarineau\AdminPhp\Console\MakeModuleCommand;
use Gmarineau\AdminPhp\Console\RegenerateMediaCommand;
use Gmarineau\AdminPhp\Console\SyncMediaUsagesCommand;
use Gmarineau\AdminPhp\Console\UserCommand;
use Gmarineau\AdminPhp\Enums\PublicationStatus;
use Gmarineau\AdminPhp\Models\Block;
use Gmarineau\AdminPhp\Models\User;
use Gmarineau\AdminPhp\Settings\SettingsStore;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminPhpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/adminphp.php', 'adminphp');

        $this->app->singleton(AdminPhp::class);
        $this->app->scoped(SettingsStore::class);

        $this->registerGuard();
    }

    public function boot(): void
    {
        Relation::morphMap(['adminphp_block' => Block::class]);

        $this->registerGates();

        $this->registerBlueprintMacros();

        $this->keepAdminInputUntouched();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'adminphp');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, UserCommand::class, RegenerateMediaCommand::class, SyncMediaUsagesCommand::class, MakeModuleCommand::class, MakeBlockCommand::class]);

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
     * Laravel trims every input string and turns empty ones into null. Rich text is sent
     * as a tree of text nodes whose leading and trailing spaces are meaningful, so the
     * admin API receives its input as typed; fields normalize their own values.
     */
    protected function keepAdminInputUntouched(): void
    {
        $isAdminApiRequest = fn (Request $request): bool => $request->is(trim((string) config('adminphp.path'), '/').'/api/*');

        TrimStrings::skipWhen($isAdminApiRequest);
        ConvertEmptyStringsToNull::skipWhen($isAdminApiRequest);
    }

    /**
     * Adds the admin session guard, its user provider and its password brokers
     * (forgotten passwords and invitations) to the auth config, unless the
     * application defines them itself.
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

        $tokensTable = $config->get('adminphp.table_prefix', 'adminphp_').'password_reset_tokens';

        $brokers = [
            AdminPhp::PASSWORD_BROKER => (int) $config->get('adminphp.passwords.reset_expire', 60),
            AdminPhp::INVITATION_BROKER => (int) $config->get('adminphp.passwords.invitation_expire', 10080),
        ];

        foreach ($brokers as $broker => $expireMinutes) {
            if (! $config->has("auth.passwords.{$broker}")) {
                $config->set("auth.passwords.{$broker}", [
                    'provider' => 'adminphp_users',
                    'table' => $tokensTable,
                    'expire' => $expireMinutes,
                    'throttle' => 60,
                ]);
            }
        }
    }

    /**
     * Only admins manage the other admin users and the site settings.
     */
    protected function registerGates(): void
    {
        Gate::define(AdminPhp::MANAGE_USERS, fn (mixed $user): bool => $user instanceof User && $user->isAdmin() && $user->isActive());
        Gate::define(AdminPhp::MANAGE_SETTINGS, fn (mixed $user): bool => $user instanceof User && $user->isAdmin() && $user->isActive());
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

        Blueprint::macro('position', function (): void {
            /** @var Blueprint $this */
            $this->unsignedInteger('position')->default(0);
        });

        Blueprint::macro('nesting', function (): void {
            /** @var Blueprint $this */
            // The foreign key points to the same table: Postgres needs its primary key first,
            // while a primary key declared fluently (->primary()) would only come last.
            foreach ($this->getColumns() as $column) {
                if ($column->get('primary') === true) {
                    $this->primary($column->get('name'));
                    $column->offsetUnset('primary');
                }
            }

            $this->foreignUlid('parent_id')->nullable()->constrained($this->getTable())->nullOnDelete();

            $this->index(['parent_id', 'position'], "{$this->getTable()}_tree_index");
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
