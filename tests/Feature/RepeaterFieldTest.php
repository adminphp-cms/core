<?php

use AdminPhp\Fields\Blocks;
use AdminPhp\Fields\Repeater;
use AdminPhp\Models\Block;
use AdminPhp\Tests\Fixtures\Article;
use Illuminate\Support\Str;

beforeEach(function () {
    actingAsAdmin();
});

it('stores the items with their declared fields only and reads them back', function () {
    $itemId = (string) Str::ulid();

    $id = createArticleItem(['faq' => [
        ['id' => $itemId, 'question' => ['fr' => 'Combien ?', 'en' => ''], 'answer' => ['fr' => 'Peu.'], 'extra' => 'ignoré'],
        ['question' => ['fr' => 'Quand ?']],
    ]]);

    $faq = Article::query()->findOrFail($id)->faq;

    expect($faq)->toHaveCount(2)
        ->and($faq[0])->toEqual(['id' => $itemId, 'question' => ['fr' => 'Combien ?'], 'answer' => ['fr' => 'Peu.']])
        ->and(Str::isUlid($faq[1]['id']))->toBeTrue()
        ->and($faq[1]['answer'])->toBe([]);

    $this->getJson("/admin/api/modules/article/items/{$id}")->assertJsonPath('data.values.faq', $faq);
});

it('names the item fields in validation messages', function () {
    $this->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => 'Titre'],
        'faq' => [['question' => ['fr' => 'Une']], ['question' => ['en' => 'Two']]],
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'faq.1.question.fr' => 'The FAQ 2 · Question (FR) field is required.',
    ]);
});

it('rejects more items than its maximum', function () {
    $this->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => 'Titre'],
        'faq' => array_fill(0, 4, ['question' => ['fr' => 'Une']]),
    ])->assertUnprocessable()->assertJsonValidationErrors(['faq']);
});

it('stores the items of a repeater in a block with its content', function () {
    $id = createArticleItem(['content' => [['type' => 'links', 'content' => ['links' => [['label' => ['fr' => 'Accueil'], 'url' => '/']]]]]]);

    $block = Block::query()->whereMorphedTo('blockable', Article::query()->findOrFail($id))->sole();

    expect($block->content['links'][0])->toMatchArray(['label' => ['fr' => 'Accueil'], 'url' => '/']);

    $this->postJson('/admin/api/modules/article/items', [
        'title' => ['fr' => 'Titre'],
        'content' => [['type' => 'links', 'content' => ['links' => [['label' => ['fr' => 'Accueil']]]]]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['content.0.content.links.0.url']);
});

it('refuses a blocks field among its item fields', function () {
    Repeater::make('faq')->fields([Blocks::make('content')]);
})->throws(LogicException::class, 'Repeater [faq] cannot contain a Blocks field.');
