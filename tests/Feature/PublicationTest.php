<?php

use AdminPhp\Enums\PublicationStatus;
use AdminPhp\Enums\RevisionStatus;
use AdminPhp\Models\Revision;
use AdminPhp\Tests\Fixtures\Article;
use Illuminate\Support\Str;

beforeEach(function () {
    actingAsAdmin();
});

it('creates items as unpublished drafts and saves their edits in place', function () {
    $id = createArticleItem();

    $this->putJson("/admin/api/modules/article/items/{$id}", ['title' => ['fr' => 'Version 2']])
        ->assertOk()
        ->assertJsonPath('data.publication.status', 'draft')
        ->assertJsonPath('data.publication.visibility', 'draft')
        ->assertJsonPath('data.publication.hasDraft', false);

    expect(Article::query()->findOrFail($id)->getTranslation('title', 'fr'))->toBe('Version 2')
        ->and(Article::query()->published()->count())->toBe(0)
        ->and(Revision::query()->count())->toBe(0);
});

it('publishes an item and keeps a snapshot of the published version', function () {
    $id = createArticleItem();

    $this->postJson("/admin/api/modules/article/items/{$id}/publish")
        ->assertOk()
        ->assertJsonPath('data.publication.status', 'published')
        ->assertJsonPath('data.publication.visibility', 'live');

    $article = Article::query()->findOrFail($id);

    expect($article->status)->toBe(PublicationStatus::Published)
        ->and($article->published_at)->not->toBeNull()
        ->and(Article::query()->published()->pluck('id')->all())->toBe([$id])
        ->and($article->revisions()->sole()->payload['title'])->toBe(['fr' => 'Version 1']);
});

it('keeps the live values of a published item while its changes wait in a single draft', function () {
    $id = createArticleItem();
    publishArticleItem($id);

    $blockId = (string) Str::ulid();

    $this->putJson("/admin/api/modules/article/items/{$id}", ['title' => ['fr' => 'Version 2']])->assertOk();

    $this->putJson("/admin/api/modules/article/items/{$id}", [
        'content' => [['id' => $blockId, 'type' => 'quote', 'content' => ['quote' => ['fr' => 'Nouveau bloc']]]],
    ])
        ->assertOk()
        ->assertJsonPath('data.values.title', ['fr' => 'Version 2'])
        ->assertJsonPath('data.values.content.0.id', $blockId)
        ->assertJsonPath('data.publication.hasDraft', true);

    $article = Article::query()->findOrFail($id);

    expect($article->getTranslation('title', 'fr'))->toBe('Version 1')
        ->and($article->blocks)->toBeEmpty()
        ->and($article->revisions()->where('status', RevisionStatus::Draft)->count())->toBe(1);
});

it('applies the draft when publishing again', function () {
    $id = createArticleItem();
    publishArticleItem($id);

    $blockId = (string) Str::ulid();

    $this->putJson("/admin/api/modules/article/items/{$id}", [
        'title' => ['fr' => 'Version 2'],
        'content' => [['id' => $blockId, 'type' => 'quote', 'content' => ['quote' => ['fr' => 'Nouveau bloc']]]],
    ])->assertOk();

    publishArticleItem($id, ['values' => ['lead' => ['fr' => 'Chapeau non enregistré']]]);

    $article = Article::query()->findOrFail($id);

    expect($article->getTranslation('title', 'fr'))->toBe('Version 2')
        ->and($article->getTranslation('lead', 'fr'))->toBe('Chapeau non enregistré')
        ->and($article->blocks->pluck('id')->all())->toBe([$blockId])
        ->and($article->draftRevision)->toBeNull()
        ->and($article->revisions()->pluck('status')->all())->toBe([RevisionStatus::Published, RevisionStatus::Published]);
});

it('does not publish anything when the draft no longer validates', function () {
    $id = createArticleItem();

    $this->postJson("/admin/api/modules/article/items/{$id}/publish", ['values' => ['title' => ['en' => 'No French']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title.fr']);

    expect(Article::query()->findOrFail($id)->status)->toBe(PublicationStatus::Draft)
        ->and(Revision::query()->count())->toBe(0);
});

it('schedules a publication window', function () {
    $id = createArticleItem();

    publishArticleItem($id, ['publishStartAt' => now()->addDay()->toIso8601String()]);

    $this->getJson("/admin/api/modules/article/items/{$id}")->assertJsonPath('data.publication.visibility', 'scheduled');
    expect(Article::query()->published()->count())->toBe(0);

    $this->travel(2)->days();
    expect(Article::query()->published()->count())->toBe(1);

    publishArticleItem($id, ['publishEndAt' => now()->addHour()->toIso8601String()]);
    $this->travel(2)->hours();

    $this->getJson("/admin/api/modules/article/items/{$id}")->assertJsonPath('data.publication.visibility', 'expired');
    expect(Article::query()->published()->count())->toBe(0);
});

it('rejects a publication window that ends before it starts', function () {
    $id = createArticleItem();

    $this->postJson("/admin/api/modules/article/items/{$id}/publish", [
        'publishStartAt' => now()->addDays(2)->toIso8601String(),
        'publishEndAt' => now()->addDay()->toIso8601String(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['publishEndAt']);
});

it('discards the pending draft', function () {
    $id = createArticleItem();
    publishArticleItem($id);
    $this->putJson("/admin/api/modules/article/items/{$id}", ['title' => ['fr' => 'Version 2']]);

    $this->deleteJson("/admin/api/modules/article/items/{$id}/draft")
        ->assertOk()
        ->assertJsonPath('data.values.title', ['fr' => 'Version 1'])
        ->assertJsonPath('data.publication.hasDraft', false);
});

it('applies the pending draft when unpublishing', function () {
    $id = createArticleItem();
    publishArticleItem($id);
    $this->putJson("/admin/api/modules/article/items/{$id}", ['title' => ['fr' => 'Version 2']]);

    $this->postJson("/admin/api/modules/article/items/{$id}/unpublish")
        ->assertOk()
        ->assertJsonPath('data.publication.status', 'draft')
        ->assertJsonPath('data.publication.hasDraft', false);

    expect(Article::query()->findOrFail($id)->getTranslation('title', 'fr'))->toBe('Version 2')
        ->and(Article::query()->published()->count())->toBe(0);
});

it('lists the published versions and restores one as the draft', function () {
    $id = createArticleItem();
    publishArticleItem($id);
    publishArticleItem($id, ['values' => ['title' => ['fr' => 'Version 2']]]);

    $revisions = $this->getJson("/admin/api/modules/article/items/{$id}/revisions")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data');

    $firstVersionId = end($revisions)['id'];

    $this->postJson("/admin/api/modules/article/items/{$id}/revisions/{$firstVersionId}/restore")
        ->assertOk()
        ->assertJsonPath('data.values.title', ['fr' => 'Version 1'])
        ->assertJsonPath('data.publication.hasDraft', true);

    expect(Article::query()->findOrFail($id)->getTranslation('title', 'fr'))->toBe('Version 2');
});

it('cannot restore a revision of another item', function () {
    $firstId = createArticleItem();
    publishArticleItem($firstId);
    $otherId = createArticleItem();

    $revisionId = Article::query()->findOrFail($firstId)->revisions()->value('id');

    $this->postJson("/admin/api/modules/article/items/{$otherId}/revisions/{$revisionId}/restore")->assertNotFound();
});

it('shows the visibility and pending draft in the listing', function () {
    $id = createArticleItem();
    publishArticleItem($id);
    $this->putJson("/admin/api/modules/article/items/{$id}", ['title' => ['fr' => 'Version 2']]);

    $this->getJson('/admin/api/modules/article/items')
        ->assertJsonPath('data.0.visibility', 'live')
        ->assertJsonPath('data.0.hasDraft', true);

    $this->getJson('/admin/api/modules/article')->assertJsonPath('data.publishable', true);
});

it('records who saved the draft and who published each version', function () {
    $author = actingAsAdmin();
    $id = createArticleItem();
    publishArticleItem($id);

    $this->getJson("/admin/api/modules/article/items/{$id}/revisions")
        ->assertJsonPath('data.0.userName', $author->name);

    $editor = actingAsAdmin();
    $this->putJson("/admin/api/modules/article/items/{$id}", ['title' => ['fr' => 'Version 2']])
        ->assertJsonPath('data.publication.draftUpdatedBy', $editor->name);
});
