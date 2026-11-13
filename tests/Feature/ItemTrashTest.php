<?php

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Models\Block;
use Gmarineau\AdminPhp\Models\Related;
use Gmarineau\AdminPhp\Models\Revision;
use Gmarineau\AdminPhp\Tests\Fixtures\Article;
use Gmarineau\AdminPhp\Tests\Fixtures\MenuLinkModule;
use Gmarineau\AdminPhp\Tests\Fixtures\Page;
use Gmarineau\AdminPhp\Tests\Fixtures\PageModule;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    actingAsAdmin();
});

/**
 * @param  array<int, string>  $ids
 */
function bulkArticles(string $action, array $ids): TestResponse
{
    return test()->postJson('/admin/api/modules/article/items/bulk', ['action' => $action, 'ids' => $ids]);
}

it('moves an item to the trash, out of the listing and the site', function () {
    $trashed = createArticleItem(['title' => ['fr' => 'À jeter']]);
    $kept = createArticleItem(['related_articles' => [['type' => 'article', 'id' => $trashed]]]);
    publishArticleItem($trashed);

    $this->deleteJson("/admin/api/modules/article/items/{$trashed}")->assertNoContent();

    expect(Article::query()->find($trashed))->toBeNull()
        ->and(Article::withTrashed()->find($trashed)->trashed())->toBeTrue()
        ->and(Article::query()->findOrFail($kept)->related('related_articles'))->toBeEmpty();

    $this->getJson("/admin/api/modules/article/items/{$trashed}")->assertNotFound();
    $this->getJson('/admin/api/modules/article/items')->assertJsonPath('meta.total', 1)->assertJsonPath('meta.counts.trash', 1);
    $this->getJson('/admin/api/modules/article/items?view=trash')->assertJsonPath('data.0.id', $trashed);
});

it('restores trashed items with their content', function () {
    $id = createArticleItem(['title' => ['fr' => 'Revenu']]);
    $this->deleteJson("/admin/api/modules/article/items/{$id}")->assertNoContent();

    bulkArticles('restore', [$id])->assertOk()->assertExactJson(['data' => ['done' => 1, 'failed' => []]]);

    $this->getJson("/admin/api/modules/article/items/{$id}")->assertOk()->assertJsonPath('data.values.title.fr', 'Revenu');
});

it('deletes trashed items for good with their blocks, revisions and browser choices', function () {
    $other = createArticleItem();
    $id = createArticleItem([
        'content' => [['type' => 'quote', 'content' => ['quote' => ['fr' => 'Citation']]]],
        'related_articles' => [['type' => 'article', 'id' => $other]],
    ]);
    publishArticleItem($id);
    $this->putJson("/admin/api/modules/article/items/{$other}", ['related_articles' => [['type' => 'article', 'id' => $id]]])->assertOk();
    $live = createArticleItem();

    $this->deleteJson("/admin/api/modules/article/items/{$id}")->assertNoContent();

    bulkArticles('delete', [$id, $live])->assertOk()->assertJsonPath('data.done', 1);

    expect(Article::withTrashed()->find($id))->toBeNull()
        ->and(Article::query()->find($live))->not->toBeNull()
        ->and(Block::query()->count())->toBe(0)
        ->and(Revision::query()->where('revisionable_id', $id)->count())->toBe(0)
        ->and(Related::query()->count())->toBe(0);
});

it('answers 404 when trashing an item of a module without trash', function () {
    app(AdminPhp::class)->registerModules([MenuLinkModule::class]);
    $id = $this->postJson('/admin/api/modules/menu-link/items', ['title' => ['fr' => 'Accueil']])->json('data.id');

    $this->deleteJson("/admin/api/modules/menu-link/items/{$id}")->assertNotFound();
    $this->postJson('/admin/api/modules/menu-link/items/bulk', ['action' => 'publish', 'ids' => [$id]])->assertNotFound();
});

it('duplicates an item as edited into an unpublished copy with new slugs and blocks', function () {
    $id = createArticleItem([
        'title' => ['fr' => 'Charpente', 'en' => 'Framing'],
        'content' => [['type' => 'quote', 'content' => ['quote' => ['fr' => 'Citation']]]],
        'related_articles' => [['type' => 'article', 'id' => createArticleItem()]],
    ]);
    publishArticleItem($id);
    $this->putJson("/admin/api/modules/article/items/{$id}", ['lead' => ['fr' => 'Chapeau du brouillon']])->assertOk();

    $response = $this->postJson("/admin/api/modules/article/items/{$id}/duplicate")
        ->assertCreated()
        ->assertJsonPath('data.title', 'Charpente (copie)')
        ->assertJsonPath('data.values.title', ['fr' => 'Charpente (copie)', 'en' => 'Framing (copie)'])
        ->assertJsonPath('data.values.lead.fr', 'Chapeau du brouillon')
        ->assertJsonPath('data.values.slug.fr', 'charpente-copie')
        ->assertJsonPath('data.publication.status', 'draft');

    $copy = Article::query()->findOrFail($response->json('data.id'));
    $blocks = Block::query()->where('content->quote->fr', 'Citation')->get();

    expect($blocks)->toHaveCount(2)
        ->and($blocks->pluck('blockable_id')->sort()->values()->all())->toBe(collect([$id, $copy->id])->sort()->values()->all())
        ->and($copy->related('related_articles', onlyPublished: false))->toHaveCount(1);
});

it('publishes several items at once and reports the ones whose content is not valid', function () {
    $valid = createArticleItem();
    $invalid = (string) Article::query()->forceCreate(['title' => ['en' => 'No French title']])->getKey();

    bulkArticles('publish', [$valid, $invalid])->assertOk()->assertExactJson(['data' => ['done' => 1, 'failed' => [$invalid]]]);

    expect(Article::query()->findOrFail($valid)->isPublished())->toBeTrue()
        ->and(Article::query()->findOrFail($invalid)->isPublished())->toBeFalse();

    bulkArticles('unpublish', [$valid])->assertOk()->assertJsonPath('data.done', 1);

    expect(Article::query()->findOrFail($valid)->isPublished())->toBeFalse();
});

it('refuses an unknown bulk action', function () {
    bulkArticles('archive', [createArticleItem()])->assertUnprocessable()->assertJsonValidationErrors(['action']);
});

it('keeps the slug of a trashed item for when it is restored', function () {
    $trashed = createArticleItem(['title' => ['fr' => 'Charpente']]);
    $this->deleteJson("/admin/api/modules/article/items/{$trashed}")->assertNoContent();

    $other = createArticleItem(['title' => ['fr' => 'Charpente']]);
    bulkArticles('restore', [$trashed])->assertOk();

    expect(Article::query()->find($other)->getTranslation('slug', 'fr'))->toBe('charpente-2')
        ->and(Article::query()->find($trashed)->getTranslation('slug', 'fr'))->toBe('charpente');
});

/**
 * Creates a page through the admin API, under the given parent, and returns its id.
 */
function createPage(string $title, ?string $parentId = null): string
{
    $id = test()->postJson('/admin/api/modules/page/items', ['title' => ['fr' => $title]])->assertCreated()->json('data.id');

    Page::query()->whereKey($id)->update(['parent_id' => $parentId]);

    return $id;
}

/**
 * @param  array<int, string>  $ids
 */
function bulkPages(string $action, array $ids): TestResponse
{
    return test()->postJson('/admin/api/modules/page/items/bulk', ['action' => $action, 'ids' => $ids]);
}

it('trashes a nested item with its descendants and restores those trashed with it', function () {
    app(AdminPhp::class)->registerModules([PageModule::class]);
    $parent = createPage('Atelier');
    $child = createPage('Machines', $parent);
    $grandChild = createPage('Scie', $child);
    $trashedBefore = createPage('Ancienne page', $parent);
    $this->deleteJson("/admin/api/modules/page/items/{$trashedBefore}")->assertNoContent();
    $this->travel(1)->minute();

    $this->deleteJson("/admin/api/modules/page/items/{$parent}")->assertNoContent();

    expect(Page::query()->count())->toBe(0);

    bulkPages('restore', [$parent])->assertOk();

    expect(Page::query()->pluck('id')->sort()->values()->all())->toBe(collect([$parent, $child, $grandChild])->sort()->values()->all())
        ->and(Page::query()->find($grandChild)->parent_id)->toBe($child)
        ->and(Page::onlyTrashed()->pluck('id')->all())->toBe([$trashedBefore]);
});

it('moves an item restored without its parent to the top of the tree', function () {
    app(AdminPhp::class)->registerModules([PageModule::class]);
    $root = createPage('Accueil');
    $parent = createPage('Atelier');
    $child = createPage('Machines', $parent);
    $this->deleteJson("/admin/api/modules/page/items/{$parent}")->assertNoContent();

    bulkPages('restore', [$child])->assertOk();

    $restored = Page::query()->find($child);

    expect($restored->parent_id)->toBeNull()
        ->and($restored->position)->toBeGreaterThan(Page::query()->find($root)->position);
});
