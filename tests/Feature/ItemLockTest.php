<?php

use AdminPhp\AdminPhp;
use AdminPhp\Models\Lock;
use AdminPhp\Models\User;
use AdminPhp\Tests\Fixtures\Article;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->claire = actingAsAdmin();
    $this->claire->update(['name' => 'Claire']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
    $this->articleId = createArticleItem();
});

function switchToAdmin(User $user): void
{
    test()->actingAs($user, app(AdminPhp::class)->guardName());
}

function lockArticle(string $id, bool $takeOver = false): TestResponse
{
    return test()->postJson("/admin/api/modules/article/items/{$id}/lock", ['takeOver' => $takeOver])->assertOk();
}

it('gives the lock to the first editor and tells the next one who holds it', function () {
    lockArticle($this->articleId)->assertJsonPath('data.isMine', true);

    switchToAdmin($this->bob);

    lockArticle($this->articleId)
        ->assertJsonPath('data.isMine', false)
        ->assertJsonPath('data.userName', 'Claire');

    expect(Lock::query()->sole()->user_id)->toBe($this->claire->id);
});

it('renews the lock of its holder without changing since when they edit', function () {
    $this->travelTo('2026-11-13 10:00:00');
    lockArticle($this->articleId);
    $this->travelTo('2026-11-13 10:01:00');
    lockArticle($this->articleId)->assertJsonPath('data.lockedAt', '2026-11-13T10:00:00+00:00');

    expect(Lock::query()->sole()->renewed_at->toTimeString())->toBe('10:01:00');
});

it('releases a lock that is no longer renewed', function () {
    $this->travelTo('2026-11-13 10:00:00');
    lockArticle($this->articleId);

    $this->travelTo('2026-11-13 10:02:01');
    switchToAdmin($this->bob);

    lockArticle($this->articleId)->assertJsonPath('data.isMine', true);
});

it('lets someone take over, after which the previous editor can no longer save', function () {
    lockArticle($this->articleId);

    switchToAdmin($this->bob);
    lockArticle($this->articleId, takeOver: true)->assertJsonPath('data.isMine', true);

    switchToAdmin($this->claire);
    $message = 'Bob modifie ce contenu en ce moment : vos changements ne peuvent pas être enregistrés.';

    $this->putJson("/admin/api/modules/article/items/{$this->articleId}", ['title' => ['fr' => 'Écrasé']])->assertStatus(423)->assertJsonPath('message', $message);
    $this->postJson("/admin/api/modules/article/items/{$this->articleId}/publish")->assertStatus(423);
    $this->deleteJson("/admin/api/modules/article/items/{$this->articleId}")->assertStatus(423);

    expect(Article::query()->findOrFail($this->articleId)->getTranslation('title', 'fr'))->toBe('Version 1');
});

it('releases the lock of its holder only', function () {
    lockArticle($this->articleId);

    switchToAdmin($this->bob);
    $this->deleteJson("/admin/api/modules/article/items/{$this->articleId}/lock")->assertNoContent();

    expect(Lock::query()->count())->toBe(1);

    switchToAdmin($this->claire);
    $this->deleteJson("/admin/api/modules/article/items/{$this->articleId}/lock")->assertNoContent();

    expect(Lock::query()->count())->toBe(0);
});

it('shows in the listing who edits an item, except to its own editor', function () {
    lockArticle($this->articleId);

    $this->getJson('/admin/api/modules/article/items')->assertJsonPath('data.0.lockedBy', null);

    switchToAdmin($this->bob);

    $this->getJson('/admin/api/modules/article/items')->assertJsonPath('data.0.lockedBy', 'Claire');
});

it('leaves items someone else is editing out of bulk actions', function () {
    $free = createArticleItem();
    lockArticle($this->articleId);

    switchToAdmin($this->bob);

    $this->postJson('/admin/api/modules/article/items/bulk', ['action' => 'trash', 'ids' => [$this->articleId, $free]])
        ->assertOk()
        ->assertExactJson(['data' => ['done' => 1, 'failed' => [$this->articleId]]]);

    expect(Article::query()->find($this->articleId))->not->toBeNull();
});
