<?php

use AdminPhp\Models\Block;

function blockWithContent(array $content): Block
{
    return new Block(['type' => 'quote', 'content' => $content]);
}

it('reads a translated field in the current locale', function () {
    app()->setLocale('en');

    expect(blockWithContent(['quote' => ['fr' => 'Bonjour', 'en' => 'Hello']])->translated('quote'))->toBe('Hello');
});

it('falls back on the default locale, then on any filled locale', function () {
    expect(blockWithContent(['quote' => ['fr' => 'Bonjour', 'en' => '']])->translated('quote', 'en'))->toBe('Bonjour')
        ->and(blockWithContent(['quote' => ['en' => 'Hello']])->translated('quote', 'de'))->toBe('Hello')
        ->and(blockWithContent(['quote' => []])->translated('quote'))->toBeNull();
});

it('returns untranslated values and rich text documents as they are', function () {
    $document = ['type' => 'doc', 'content' => []];

    expect(blockWithContent(['author' => 'Claire'])->translated('author'))->toBe('Claire')
        ->and(blockWithContent(['tags' => [1, 2]])->translated('tags'))->toBe([1, 2])
        ->and(blockWithContent(['body' => ['fr' => $document]])->translated('body'))->toBe($document)
        ->and(blockWithContent([])->translated('missing'))->toBeNull();
});
