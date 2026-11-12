<?php

namespace Gmarineau\AdminPhp;

use Closure;
use Gmarineau\AdminPhp\Models\User;
use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use RuntimeException;

class AdminPhp
{
    /** Password broker of "forgotten password" links. */
    public const PASSWORD_BROKER = 'adminphp_users';

    /** Password broker of invitation links, valid longer. */
    public const INVITATION_BROKER = 'adminphp_invitations';

    /** Gate ability to manage the other admin users. */
    public const MANAGE_USERS = 'adminphp.manage-users';

    /**
     * @var array<string, Module>
     */
    protected array $modules = [];

    /**
     * @var (Closure(Request): bool)|null
     */
    protected ?Closure $authUsing = null;

    /**
     * Restricts the admin further than "logged in as an admin user",
     * e.g. to an IP range. Called on every request of a logged in user.
     *
     * @param  Closure(Request): bool  $callback
     */
    public function auth(Closure $callback): void
    {
        $this->authUsing = $callback;
    }

    public function check(Request $request): bool
    {
        return $this->authUsing === null || (bool) ($this->authUsing)($request);
    }

    /**
     * Name of the session guard admin users log in with.
     */
    public function guardName(): string
    {
        return config('adminphp.guard', 'adminphp');
    }

    public function guard(): StatefulGuard
    {
        return Auth::guard($this->guardName());
    }

    /**
     * The admin user of the current request, if any.
     */
    public function user(): ?User
    {
        $user = $this->guard()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param  self::PASSWORD_BROKER|self::INVITATION_BROKER  $name
     */
    public function passwordBroker(string $name = self::PASSWORD_BROKER): PasswordBroker
    {
        return Password::broker($name);
    }

    /**
     * Script and style tags of the prebuilt admin front, read from the published Vite manifest.
     */
    public function assets(): HtmlString
    {
        $manifestPath = public_path('vendor/adminphp/.vite/manifest.json');

        if (! is_file($manifestPath)) {
            throw new RuntimeException('AdminPhp assets are not published. Run: php artisan vendor:publish --tag=adminphp-assets --force');
        }

        /** @var array{file: string, css?: array<int, string>} $entry */
        $entry = json_decode((string) file_get_contents($manifestPath), true)['resources/js/app.ts'];

        $tags = array_map(
            fn (string $css): string => sprintf('<link rel="stylesheet" href="%s">', e(asset("vendor/adminphp/{$css}"))),
            $entry['css'] ?? [],
        );

        $tags[] = sprintf('<script type="module" src="%s"></script>', e(asset("vendor/adminphp/{$entry['file']}")));

        return new HtmlString(implode("\n", $tags));
    }

    /**
     * Registers modules and their morph map aliases.
     *
     * @param  array<int, class-string<Module>>  $moduleClasses
     */
    public function registerModules(array $moduleClasses): void
    {
        foreach ($moduleClasses as $moduleClass) {
            $module = new $moduleClass;

            $this->modules[$module->name()] = $module;

            Relation::morphMap([$module->name() => $module->modelClass()]);
        }
    }

    /**
     * @return array<string, Module>
     */
    public function modules(): array
    {
        return $this->modules;
    }

    public function hasModule(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    public function module(string $name): Module
    {
        return $this->modules[$name]
            ?? throw new InvalidArgumentException("AdminPhp module [{$name}] is not registered.");
    }

    /**
     * @return array<int, string>
     */
    public function locales(): array
    {
        return array_values(config('adminphp.locales', ['en']));
    }

    public function defaultLocale(): string
    {
        return $this->locales()[0];
    }

    public function table(string $name): string
    {
        return config('adminphp.table_prefix', 'adminphp_').$name;
    }
}
