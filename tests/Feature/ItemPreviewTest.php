<?php

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Models\Block;
use Gmarineau\AdminPhp\Models\Related;
use Gmarineau\AdminPhp\Tests\Fixtures\Article;
use Gmarineau\AdminPhp\Tests\Fixtures\MenuLinkModule;

beforeEach(function () {
    actingAsAdmin();
});

it('previews the unsaved form of an item without writing anything', function () {
    $related = createArticleItem(['title' => ['fr' => 'Article lié']]);
    $id = createArticleItem(['title' => ['fr' => 'Titre enregistré']]);

    $html = $this->postJson("/admin/api/modules/article/items/{$id}/preview", ['values' => [
        'title' => ['fr' => 'Titre pas encore enregistré'],
        'content' => [['type' => 'quote', 'content' => ['quote' => ['fr' => 'Une citation en cours']]]],
        'related_articles' => [['type' => 'article', 'id' => $related]],
    ]])->assertOk()->json('data.html');

    expect($html)->toContain('<h1>Titre pas encore enregistré</h1>')
        ->toContain('<blockquote>Une citation en cours</blockquote>')
        ->toContain('<a>Article lié</a>')
        ->and(Article::query()->findOrFail($id)->getTranslation('title', 'fr'))->toBe('Titre enregistré')
        ->and(Block::query()->count())->toBe(0)
        ->and(Related::query()->count())->toBe(0);
});

it('previews a new item, with the slug it would get, without creating it', function () {
    $html = $this->postJson('/admin/api/modules/article/preview', ['values' => ['title' => ['fr' => 'Tout nouveau']]])
        ->assertOk()
        ->json('data.html');

    expect($html)->toContain('<h1>Tout nouveau</h1>')
        ->toContain('<p class="slug">tout-nouveau</p>')
        ->and(Article::query()->count())->toBe(0);
});

it('previews in the requested locale', function () {
    $html = $this->postJson('/admin/api/modules/article/preview', [
        'values' => ['title' => ['fr' => 'Bonjour', 'en' => 'Hello']],
        'locale' => 'en',
    ])->json('data.html');

    expect($html)->toContain('<h1>Hello</h1>');
});

it('explains why a preview could not be shown when the view fails', function () {
    $this->postJson('/admin/api/modules/article/preview', ['values' => ['title' => ['fr' => 'Erreur']]])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'L’aperçu n’a pas pu être affiché : Vue cassée');
});

it('answers 404 for a module without preview view', function () {
    app(AdminPhp::class)->registerModules([MenuLinkModule::class]);

    $this->getJson('/admin/api/modules/menu-link')->assertJsonPath('data.previewable', false);
    $this->getJson('/admin/api/modules/article')->assertJsonPath('data.previewable', true);
    $this->postJson('/admin/api/modules/menu-link/preview', ['values' => []])->assertNotFound();
});
