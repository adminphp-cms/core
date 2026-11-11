<?php

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Tests\Fixtures\Article;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    actingAsAdmin();
});

it('denies access when the auth callback refuses the request', function () {
    app(AdminPhp::class)->auth(fn () => false);

    $this->getJson('/admin/api/modules')->assertForbidden();
});

it('lists the registered modules with their item count', function () {
    Article::query()->forceCreate(['title' => ['fr' => 'Un']]);

    $this->getJson('/admin/api/modules')
        ->assertOk()
        ->assertExactJson(['data' => [['name' => 'article', 'title' => 'Articles', 'count' => 1]]]);
});

it('returns the form schema of a module', function () {
    $this->getJson('/admin/api/modules/article')
        ->assertOk()
        ->assertJsonPath('data.name', 'article')
        ->assertJsonPath('data.fields.0.name', 'title')
        ->assertJsonPath('data.fields.2.blocks.1.type', 'quote');
});

it('answers 404 for an unknown module', function () {
    $this->getJson('/admin/api/modules/unknown')->assertNotFound();
    $this->getJson('/admin/api/modules/unknown/items')->assertNotFound();
});

it('creates, lists, reads and updates an item', function () {
    $blockId = (string) Str::ulid();

    $id = $this->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => 'Rénover une maison', 'en' => 'Renovating a house'],
        'content' => [['id' => $blockId, 'type' => 'quote', 'content' => ['quote' => ['fr' => 'Le toit d’abord.']]]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Rénover une maison')
        ->assertJsonPath('data.values.content.0.id', $blockId)
        ->json('data.id');

    $this->getJson('/admin/api/modules/article/items')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('data.0.title', 'Rénover une maison')
        ->assertJsonPath('meta.total', 1);

    $this->putJson("/admin/api/modules/article/items/{$id}", ['title' => ['fr' => 'Nouveau titre']])
        ->assertOk()
        ->assertJsonPath('data.values.title', ['fr' => 'Nouveau titre'])
        ->assertJsonPath('data.values.content.0.id', $blockId);

    $this->getJson("/admin/api/modules/article/items/{$id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Nouveau titre');
});

it('returns validation errors keyed by input path', function () {
    $this->postJson('/admin/api/modules/article/items', [
        'title' => ['en' => 'Only English'],
        'content' => [['type' => 'quote', 'content' => []]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title.fr', 'content.0.content.quote.fr'])
        ->assertJsonPath('errors', fn (array $errors): bool => str_contains($errors['title.fr'][0], 'Titre (FR)')
            && str_contains($errors['content.0.content.quote.fr'][0], 'Citation (FR)'));
});

it('answers 404 for an unknown item', function () {
    $this->getJson('/admin/api/modules/article/items/'.Str::ulid())->assertNotFound();
});

it('serves the admin page with the published assets for any admin path', function () {
    $publicPath = sys_get_temp_dir().'/adminphp-public-'.Str::random(8);
    app()->usePublicPath($publicPath);

    File::ensureDirectoryExists("{$publicPath}/vendor/adminphp/.vite");
    File::put("{$publicPath}/vendor/adminphp/.vite/manifest.json", json_encode([
        'resources/js/app.ts' => ['file' => 'assets/app.js', 'css' => ['assets/app.css']],
    ]));

    try {
        $this->get('/admin/article/123')
            ->assertOk()
            ->assertSee('<div id="adminphp"></div>', escape: false)
            ->assertSee('vendor/adminphp/assets/app.js')
            ->assertSee('"apiUrl":"\/admin\/api"', escape: false);
    } finally {
        File::deleteDirectory($publicPath);
    }
});

it('keeps the spaces around rich text nodes but trims plain text fields', function () {
    $document = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Un '],
        ['type' => 'text', 'text' => 'lien ', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => 'ici'],
    ]]]];

    $this->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => '  Titre avec espaces  ', 'en' => '   '],
        'content' => [['type' => 'text', 'content' => ['body' => ['fr' => $document]]]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.values.title', ['fr' => 'Titre avec espaces'])
        ->assertJsonPath('data.values.content.0.content.body.fr', $document);
});
