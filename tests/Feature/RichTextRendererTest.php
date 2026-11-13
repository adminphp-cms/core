<?php

use AdminPhp\Models\Block;
use AdminPhp\RichTextRenderer;
use Illuminate\Support\Facades\Blade;

/**
 * @param  array<int, array<string, mixed>>  $content
 * @return array<string, mixed>
 */
function richTextDocument(array $content): array
{
    return ['type' => 'doc', 'content' => $content];
}

/**
 * @param  array<int, array<string, mixed>>  $marks
 * @return array<string, mixed>
 */
function richTextNode(string $text, array $marks = []): array
{
    return ['type' => 'text', 'text' => $text, 'marks' => $marks];
}

function renderRichText(mixed $document): string
{
    return (string) app(RichTextRenderer::class)->render($document);
}

it('renders every node the editor produces', function () {
    $html = renderRichText(richTextDocument([
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [richTextNode('Titre')]],
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [richTextNode('Sous-titre')]],
        ['type' => 'paragraph', 'content' => [richTextNode('Un'), ['type' => 'hardBreak'], richTextNode('deux')]],
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [richTextNode('Puce')]]]]]],
        ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [richTextNode('Numéro')]]]]]],
        ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [richTextNode('Citation')]]]],
        ['type' => 'horizontalRule'],
        ['type' => 'paragraph'],
    ]));

    expect($html)->toBe(
        '<h2>Titre</h2><h3>Sous-titre</h3><p>Un<br>deux</p>'
        .'<ul><li><p>Puce</p></li></ul><ol><li><p>Numéro</p></li></ol>'
        .'<blockquote><p>Citation</p></blockquote><hr><p></p>'
    );
});

it('renders the marks, nested in their order', function () {
    $html = renderRichText(richTextDocument([['type' => 'paragraph', 'content' => [
        richTextNode('gras italique', [['type' => 'bold'], ['type' => 'italic']]),
        richTextNode('souligné', [['type' => 'underline']]),
        richTextNode('barré', [['type' => 'strike']]),
        richTextNode('code', [['type' => 'code']]),
    ]]]));

    expect($html)->toBe('<p><em><strong>gras italique</strong></em><u>souligné</u><s>barré</s><code>code</code></p>');
});

it('renders the safe links only', function (string $href, string $expected) {
    $html = renderRichText(richTextDocument([['type' => 'paragraph', 'content' => [
        richTextNode('lien', [['type' => 'link', 'attrs' => ['href' => $href]]]),
    ]]]));

    expect($html)->toBe("<p>{$expected}</p>");
})->with([
    'web' => ['https://example.com/?a=1&b=2', '<a href="https://example.com/?a=1&amp;b=2" rel="noopener">lien</a>'],
    'site' => ['/contact', '<a href="/contact">lien</a>'],
    'anchor' => ['#haut', '<a href="#haut">lien</a>'],
    'mail' => ['mailto:hello@example.com', '<a href="mailto:hello@example.com">lien</a>'],
    'phone' => ['tel:+41000000000', '<a href="tel:+41000000000">lien</a>'],
    'javascript' => ['javascript:alert(1)', 'lien'],
    'javascript in capitals' => [' JavaScript:alert(1)', 'lien'],
    'data' => ['data:text/html,<script>', 'lien'],
    'quote breaking out' => ['https://example.com" onclick="x', '<a href="https://example.com&quot; onclick=&quot;x" rel="noopener">lien</a>'],
]);

it('escapes every text', function () {
    $html = renderRichText(richTextDocument([['type' => 'paragraph', 'content' => [richTextNode('<script>alert("x")</script> & co')]]]));

    expect($html)->toBe('<p>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; co</p>');
});

it('keeps the content of unknown nodes and drops unknown marks', function () {
    $html = renderRichText(richTextDocument([
        ['type' => 'codeBlock', 'content' => [richTextNode('texte', [['type' => 'highlight'], 'pas une marque'])]],
        ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [richTextNode('Trop haut')]],
        'pas un nœud',
    ]));

    expect($html)->toBe('texte<h2>Trop haut</h2>');
});

it('renders nothing for an empty value', function (mixed $value) {
    expect(renderRichText($value))->toBe('');
})->with([null, '', 'texte', 12]);

it('renders a rich text field of a block in the current locale', function () {
    $block = new Block(['content' => ['body' => [
        'fr' => richTextDocument([['type' => 'paragraph', 'content' => [richTextNode('Bonjour')]]]),
        'en' => richTextDocument([['type' => 'paragraph', 'content' => [richTextNode('Hello')]]]),
    ]]]);

    app()->setLocale('en');

    expect((string) $block->richText('body'))->toBe('<p>Hello</p>')
        ->and((string) $block->richText('body', 'fr'))->toBe('<p>Bonjour</p>')
        ->and((string) $block->richText('missing'))->toBe('');
});

it('renders a rich text with the blade component', function () {
    $document = richTextDocument([['type' => 'paragraph', 'content' => [richTextNode('Bonjour')]]]);

    expect(Blade::render('<x-adminphp::rich-text :content="$document" class="prose" />', ['document' => $document]))
        ->toContain('<div class="prose"><p>Bonjour</p></div>')
        ->and(trim(Blade::render('<x-adminphp::rich-text :content="null" />')))->toBe('');
});
