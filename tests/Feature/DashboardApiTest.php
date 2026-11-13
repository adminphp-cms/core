<?php

use AdminPhp\Tests\Fixtures\ArticleModule;

beforeEach(function () {
    $this->admin = actingAsAdmin();
});

it('describes each module with its icon and description', function () {
    expect((new ArticleModule)->toSchema())->toMatchArray([
        'description' => 'Actualités et récits de chantier.',
        'icon' => 'newspaper',
    ]);
});

it('counts published items, offline items and pending changes', function () {
    $publishedId = createArticleItem();
    publishArticleItem($publishedId);
    $this->putJson("/admin/api/modules/article/items/{$publishedId}", ['title' => ['fr' => 'Version 2']]);

    createArticleItem();
    createArticleItem();

    $this->getJson('/admin/api/modules')
        ->assertJsonPath('data.0.count', 3)
        ->assertJsonPath('data.0.counts', ['published' => 1, 'unpublished' => 2, 'pendingChanges' => 1]);
});

it('lists the latest drafts and publications, newest first', function () {
    $id = createArticleItem(['title' => ['fr' => 'Rénover une ferme']]);
    publishArticleItem($id);

    $this->travel(1)->minute();
    $this->putJson("/admin/api/modules/article/items/{$id}", ['title' => ['fr' => 'Rénover une grange']]);

    $this->getJson('/admin/api/activity')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.action', 'saved')
        ->assertJsonPath('data.0.itemTitle', 'Rénover une ferme')
        ->assertJsonPath('data.0.userName', $this->admin->name)
        ->assertJsonPath('data.1.action', 'published')
        ->assertJsonPath('data.1.module', 'article')
        ->assertJsonPath('data.1.moduleTitle', 'Articles')
        ->assertJsonPath('data.1.icon', 'newspaper')
        ->assertJsonPath('data.1.itemId', $id);
});

it('keeps the activity for logged in admins only', function () {
    auth('adminphp')->logout();

    $this->getJson('/admin/api/activity')->assertUnauthorized();
});
