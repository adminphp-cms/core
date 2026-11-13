<?php

use AdminPhp\AdminPhp;
use AdminPhp\Fields\Blocks;
use AdminPhp\Models\Setting;
use AdminPhp\Models\User;
use AdminPhp\Settings\Settings;
use AdminPhp\Tests\Fixtures\Article;
use AdminPhp\Tests\Fixtures\SiteSettings;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app(AdminPhp::class)->registerSettings(SiteSettings::class);
});

it('answers 404 when the application declares no settings', function () {
    app(AdminPhp::class)->registerSettings(null);
    actingAsAdmin();

    $this->getJson('/admin/api/settings')->assertNotFound();
});

it('gives the settings form and its stored values', function () {
    actingAsAdmin();
    Setting::query()->create(['key' => 'site_name', 'value' => 'Atelier']);

    $this->getJson('/admin/api/settings')
        ->assertOk()
        ->assertJsonPath('data.schema.title', 'Réglages')
        ->assertJsonPath('data.schema.fields.0.name', 'site_name')
        ->assertJsonPath('data.values.site_name', 'Atelier')
        ->assertJsonPath('data.values.tagline', [])
        ->assertJsonPath('data.values.homepage', null);
});

it('saves the settings and reads them on the site', function () {
    actingAsAdmin();
    $article = Article::query()->forceCreate(['title' => ['fr' => 'Accueil']]);

    $this->putJson('/admin/api/settings', [
        'site_name' => 'Atelier',
        'tagline' => ['fr' => 'Rénover avec soin', 'en' => ''],
        'homepage' => $article->id,
        'maintenance' => true,
    ])->assertOk()->assertJsonPath('data.values.tagline', ['fr' => 'Rénover avec soin']);

    $adminPhp = app(AdminPhp::class);

    expect($adminPhp->setting('site_name'))->toBe('Atelier')
        ->and($adminPhp->setting('homepage'))->toBe($article->id)
        ->and($adminPhp->setting('maintenance'))->toBeTrue()
        ->and($adminPhp->setting('tagline', locale: 'fr'))->toBe('Rénover avec soin')
        ->and($adminPhp->setting('tagline', locale: 'en'))->toBe('Rénover avec soin')
        ->and($adminPhp->setting('missing', 'default'))->toBe('default');
});

it('validates the settings with their fields', function () {
    actingAsAdmin();

    $this->putJson('/admin/api/settings', ['site_name' => '', 'homepage' => 'not-an-article'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['site_name', 'homepage']);

    expect(Setting::query()->count())->toBe(0);
});

it('only updates the submitted settings', function () {
    actingAsAdmin();
    Setting::query()->create(['key' => 'site_name', 'value' => 'Atelier']);

    $this->putJson('/admin/api/settings', ['maintenance' => true])->assertOk();

    expect(app(AdminPhp::class)->setting('site_name'))->toBe('Atelier');
});

it('keeps editors out of the settings', function () {
    $this->actingAs(User::factory()->editor()->create(), 'adminphp');

    $this->getJson('/admin/api/settings')->assertForbidden();
    $this->putJson('/admin/api/settings', ['site_name' => 'X'])->assertForbidden();
    $this->getJson('/admin/api/session')->assertJsonPath('data.canManageSettings', false);
});

it('refuses fields that only work on a model', function () {
    $settings = new class extends Settings
    {
        public function form(): array
        {
            return [Blocks::make('content')];
        }
    };

    app(AdminPhp::class)->registerSettings($settings::class);

    expect(fn () => app(AdminPhp::class)->settingsForm())->toThrow(LogicException::class, 'Setting [content] cannot be a Blocks field.');
});

it('reads a setting without resolving lazy select options', function () {
    Setting::query()->create(['key' => 'homepage', 'value' => 'abc']);
    DB::enableQueryLog();

    expect(app(AdminPhp::class)->setting('homepage'))->toBe('abc')
        ->and(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => str_contains($query, 'articles')))->toBeEmpty();
});
