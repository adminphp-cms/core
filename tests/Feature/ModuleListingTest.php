<?php

use AdminPhp\AdminPhp;
use AdminPhp\Models\Media;
use AdminPhp\Modules\Column;
use AdminPhp\Modules\Filter;
use AdminPhp\Tests\Fixtures\Article;
use AdminPhp\Tests\Fixtures\ArticleModule;
use AdminPhp\Tests\Fixtures\MenuLinkModule;
use AdminPhp\Tests\Fixtures\TagModule;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    actingAsAdmin();
});

/**
 * @param  array<string, mixed>  $query
 */
function listArticles(array $query = []): TestResponse
{
    return test()->getJson('/admin/api/modules/article/items?'.http_build_query($query))->assertOk();
}

/**
 * @return array<int, string>
 */
function listedTitles(TestResponse $response): array
{
    return array_column($response->json('data'), 'title');
}

it('describes the columns and filters of a module from its fields', function () {
    $listing = $this->getJson('/admin/api/modules/article')->json('data.listing');

    expect($listing['columns'])->toBe([
        ['name' => 'cover', 'label' => 'Couverture', 'type' => 'image', 'sortable' => false],
        ['name' => 'title', 'label' => 'Titre', 'type' => 'title', 'sortable' => true],
        ['name' => 'status', 'label' => 'Statut', 'type' => 'status', 'sortable' => false],
        ['name' => 'category', 'label' => 'Catégorie', 'type' => 'text', 'sortable' => true],
        ['name' => 'tags', 'label' => 'Étiquettes', 'type' => 'text', 'sortable' => false],
        ['name' => 'is_featured', 'label' => 'Une', 'type' => 'boolean', 'sortable' => false],
        ['name' => 'event_on', 'label' => 'Date de l’événement', 'type' => 'date', 'sortable' => false],
        ['name' => 'updated_at', 'label' => 'Modifié', 'type' => 'datetime', 'sortable' => true],
    ])
        ->and($listing['filters'][0])->toBe(['name' => 'category', 'label' => 'Catégorie', 'options' => [
            ['value' => 'renovation', 'label' => 'Rénovation'],
            ['value' => 'garden', 'label' => 'Jardin'],
            ['value' => 'new-build', 'label' => 'Neuf'],
        ]])
        ->and($listing['filters'][2])->toBe(['name' => 'is_featured', 'label' => 'À la une', 'options' => [
            ['value' => '1', 'label' => 'Oui'],
            ['value' => '0', 'label' => 'Non'],
        ]])
        ->and($listing['defaultSort'])->toBe('-updated_at');
});

it('lists the title and last change of a module that declares no columns', function () {
    app(AdminPhp::class)->registerModules([TagModule::class]);

    expect(array_column($this->getJson('/admin/api/modules/tag')->json('data.listing.columns'), 'type'))->toBe(['title', 'datetime']);
});

it('refuses a column for a field that has no listing format and a filter on a text field', function () {
    $module = new class extends ArticleModule
    {
        public function columns(): array
        {
            return [Column::make('content')];
        }
    };

    expect(fn () => $module->toSchema())->toThrow(LogicException::class, 'Field [content] of module')
        ->and(fn () => $module->toSchema())->toThrow(LogicException::class, 'cannot be shown as a listing column.');

    $module = new class extends ArticleModule
    {
        public function filters(): array
        {
            return [Filter::make('lead')];
        }
    };

    expect(fn () => $module->toSchema())->toThrow(LogicException::class, 'Filter [lead] of module')
        ->and(fn () => $module->toSchema())->toThrow(LogicException::class, 'needs a Select or Toggle field of the same name.');
});

it('shows each cell in the format of its field', function () {
    Carbon::setTestNow('2026-11-13 09:30:00');
    $media = Media::query()->forceCreate(['disk' => 'public', 'path' => 'adminphp/cover.jpg', 'name' => 'cover.jpg', 'mime_type' => 'image/jpeg', 'size' => 1000]);

    Article::query()->forceCreate([
        'title' => ['en' => 'English only'],
        'cover' => $media->id,
        'category' => 'garden',
        'tags' => [3, 1],
        'is_featured' => true,
        'event_on' => '2026-12-05',
    ]);

    expect(listArticles()->json('data.0.cells'))->toBe([
        'cover' => $media->url(),
        'title' => 'English only',
        'status' => null,
        'category' => 'Jardin',
        'tags' => 'Isolation, Bois',
        'is_featured' => true,
        'event_on' => '2026-12-05',
        'updated_at' => '2026-11-13T09:30:00+00:00',
    ]);
});

it('sorts by a sortable column and falls back to the default sort otherwise', function () {
    Carbon::setTestNow('2026-11-01');
    createArticleItem(['title' => ['fr' => 'Charpente']]);
    Carbon::setTestNow('2026-11-02');
    createArticleItem(['title' => ['fr' => 'Arbres']]);
    Carbon::setTestNow('2026-11-03');
    createArticleItem(['title' => ['fr' => 'Bardage']]);

    expect(listedTitles(listArticles(['sort' => 'title'])))->toBe(['Arbres', 'Bardage', 'Charpente'])
        ->and(listedTitles(listArticles(['sort' => '-title'])))->toBe(['Charpente', 'Bardage', 'Arbres']);

    $response = listArticles(['sort' => 'tags']);

    expect(listedTitles($response))->toBe(['Bardage', 'Arbres', 'Charpente'])
        ->and($response->json('meta.sort'))->toBe('-updated_at');
});

it('filters by a choice, a multiple choice and a flag, ignoring unknown options', function () {
    createArticleItem(['title' => ['fr' => 'Jardin bois'], 'category' => 'garden', 'tags' => [1], 'is_featured' => true]);
    createArticleItem(['title' => ['fr' => 'Rénovation pierre'], 'category' => 'renovation', 'tags' => [2, 3]]);

    expect(listedTitles(listArticles(['filters' => ['category' => 'garden']])))->toBe(['Jardin bois'])
        ->and(listedTitles(listArticles(['filters' => ['tags' => '3']])))->toBe(['Rénovation pierre'])
        ->and(listedTitles(listArticles(['filters' => ['is_featured' => '0']])))->toBe(['Rénovation pierre'])
        ->and(listArticles(['filters' => ['category' => 'nope']])->json('meta.total'))->toBe(2);
});

it('splits a publishable module into publication views and counts them', function () {
    createArticleItem(['title' => ['fr' => 'Brouillon']]);
    $live = createArticleItem(['title' => ['fr' => 'En ligne']]);
    $changed = createArticleItem(['title' => ['fr' => 'Modifié']]);
    publishArticleItem($live);
    publishArticleItem($changed);
    $this->putJson("/admin/api/modules/article/items/{$changed}", ['lead' => ['fr' => 'Nouveau']])->assertOk();

    expect(listedTitles(listArticles(['view' => 'unpublished'])))->toBe(['Brouillon'])
        ->and(listedTitles(listArticles(['view' => 'pending'])))->toBe(['Modifié'])
        ->and(listArticles(['view' => 'published'])->json('meta.total'))->toBe(2)
        ->and(listArticles()->json('meta.counts'))->toBe(['all' => 3, 'published' => 2, 'unpublished' => 1, 'pending' => 1, 'trash' => 0]);
});

it('pages the listing by thirty', function () {
    foreach (range(1, 31) as $number) {
        Article::query()->forceCreate(['title' => ['fr' => "Article {$number}"]]);
    }

    $response = listArticles(['page' => 2]);

    expect($response->json('meta.currentPage'))->toBe(2)
        ->and($response->json('meta.lastPage'))->toBe(2)
        ->and($response->json('data'))->toHaveCount(1);
});

it('lists a sortable module whole as a tree until something narrows or reorders it', function () {
    app(AdminPhp::class)->registerModules([MenuLinkModule::class]);

    foreach (['Accueil', 'Blog', 'Contact'] as $title) {
        $this->postJson('/admin/api/modules/menu-link/items', ['title' => ['fr' => $title]])->assertCreated();
    }

    $this->getJson('/admin/api/modules/menu-link/items')
        ->assertJsonPath('meta.isTree', true)
        ->assertJsonPath('meta.sort', null)
        ->assertJsonCount(3, 'data');

    $this->getJson('/admin/api/modules/menu-link/items?search=blog')
        ->assertJsonPath('meta.isTree', false)
        ->assertJsonCount(1, 'data');

    expect(array_column($this->getJson('/admin/api/modules/menu-link/items?sort=-title')->json('data'), 'title'))->toBe(['Contact', 'Blog', 'Accueil']);
});
