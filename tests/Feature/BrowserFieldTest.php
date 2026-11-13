<?php

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Models\Block;
use Gmarineau\AdminPhp\Models\Related;
use Gmarineau\AdminPhp\Tests\Fixtures\Article;
use Gmarineau\AdminPhp\Tests\Fixtures\Tag;
use Gmarineau\AdminPhp\Tests\Fixtures\TagModule;
use Illuminate\Support\Str;

beforeEach(function () {
    app(AdminPhp::class)->registerModules([TagModule::class]);

    actingAsAdmin();
});

/**
 * @return array{type: string, id: string}
 */
function articleReference(string $id): array
{
    return ['type' => 'article', 'id' => $id];
}

it('stores the chosen contents in their order and reads them back', function () {
    $first = createArticleItem(['title' => ['fr' => 'Premier']]);
    $tag = Tag::query()->forceCreate(['name' => 'Bois']);

    $id = createArticleItem(['related_articles' => [['type' => 'tag', 'id' => $tag->id], articleReference($first)]]);

    $this->getJson("/admin/api/modules/article/items/{$id}")
        ->assertOk()
        ->assertJsonPath('data.values.related_articles', [['type' => 'tag', 'id' => $tag->id], articleReference($first)]);

    expect(Related::query()->orderBy('position')->get(['related_type', 'related_id', 'browser_name', 'position'])->toArray())->toBe([
        ['related_type' => 'tag', 'related_id' => $tag->id, 'browser_name' => 'related_articles', 'position' => 0],
        ['related_type' => 'article', 'related_id' => $first, 'browser_name' => 'related_articles', 'position' => 1],
    ]);
});

it('replaces the previous choices when the field is saved again', function () {
    $first = createArticleItem();
    $second = createArticleItem();
    $id = createArticleItem(['related_articles' => [articleReference($first), articleReference($second)]]);

    $this->putJson("/admin/api/modules/article/items/{$id}", ['related_articles' => [articleReference($second)]])->assertOk();

    expect(Related::query()->pluck('related_id')->all())->toBe([$second]);

    $this->putJson("/admin/api/modules/article/items/{$id}", ['related_articles' => []])->assertOk();

    expect(Related::query()->count())->toBe(0);
});

it('rejects a content of a module the field does not allow, a missing content and too many contents', function () {
    $first = createArticleItem();
    $second = createArticleItem();
    $third = createArticleItem();

    $this->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => 'Titre'],
        'related_articles' => [['type' => 'user', 'id' => (string) Str::ulid()], articleReference((string) Str::ulid())],
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'related_articles.0.type' => 'Articles liés',
        'related_articles.1.id' => 'Articles liés',
    ]);

    $this->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => 'Titre'],
        'related_articles' => [articleReference($first), articleReference($second), articleReference($third)],
    ])->assertUnprocessable()->assertJsonValidationErrors(['related_articles' => 'Articles liés']);
});

it('keeps the live choices of a published content until its draft is published', function () {
    $first = createArticleItem();
    $second = createArticleItem();
    $id = createArticleItem(['related_articles' => [articleReference($first)]]);
    publishArticleItem($id);

    $response = $this->putJson("/admin/api/modules/article/items/{$id}", ['related_articles' => [articleReference($second)]])->assertOk();

    expect($response->json('data.values.related_articles'))->toEqual([articleReference($second)]);

    expect(Related::query()->pluck('related_id')->all())->toBe([$first]);

    publishArticleItem($id);

    expect(Related::query()->pluck('related_id')->all())->toBe([$second]);
});

it('gives the site the chosen contents it may show, in their order', function () {
    $hidden = createArticleItem(['title' => ['fr' => 'Brouillon']]);
    $live = createArticleItem(['title' => ['fr' => 'En ligne']]);
    publishArticleItem($live);
    $id = createArticleItem(['related_articles' => [articleReference($hidden), articleReference($live)]]);

    $article = Article::query()->findOrFail($id);

    expect($article->related('related_articles')->modelKeys())->toBe([$live])
        ->and($article->related('related_articles', onlyPublished: false)->modelKeys())->toBe([$hidden, $live]);
});

it('stores the choices of a block with its content', function () {
    $live = createArticleItem();
    publishArticleItem($live);

    $id = createArticleItem(['content' => [['type' => 'links', 'content' => ['articles' => [['type' => 'article', 'id' => $live, 'title' => 'ignoré']]]]]]);

    $block = Block::query()->whereMorphedTo('blockable', Article::query()->findOrFail($id))->sole();

    expect($block->content['articles'])->toEqual([articleReference($live)])
        ->and($block->related('articles')->modelKeys())->toBe([$live])
        ->and(Related::query()->count())->toBe(0);
});

it('names the chosen contents for the admin in the given order and leaves out missing ones', function () {
    $first = createArticleItem(['title' => ['fr' => 'Premier']]);
    $tag = Tag::query()->forceCreate(['name' => 'Bois']);

    $this->getJson('/admin/api/references?'.http_build_query(['items' => ["tag:{$tag->id}", 'article:'.Str::ulid(), "article:{$first}", "unknown:{$first}"]]))
        ->assertOk()
        ->assertExactJson(['data' => [
            ['type' => 'tag', 'id' => $tag->id, 'title' => 'Bois', 'moduleTitle' => 'Tags', 'icon' => 'file-text', 'visibility' => null],
            ['type' => 'article', 'id' => $first, 'title' => 'Premier', 'moduleTitle' => 'Articles', 'icon' => 'newspaper', 'visibility' => 'draft'],
        ]]);
});

it('refuses to name contents to a guest with a 401', function () {
    app(AdminPhp::class)->guard()->logout();

    $this->getJson('/admin/api/references?items[]=article:x')->assertUnauthorized();
});

it('searches the items of a module by title in any locale', function () {
    createArticleItem(['title' => ['fr' => 'Charpente en chêne']]);
    $english = createArticleItem(['title' => ['fr' => 'Toiture', 'en' => 'Oak roof']]);
    Tag::query()->forceCreate(['name' => 'Chêne']);

    $this->getJson('/admin/api/modules/article/items?search=oak')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $english);

    $this->getJson('/admin/api/modules/article/items?search=CHARPENTE')->assertJsonPath('data.0.title', 'Charpente en chêne');
    $this->getJson('/admin/api/modules/tag/items?search=chêne')->assertJsonPath('data.0.title', 'Chêne');
});
