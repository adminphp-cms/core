<?php

use AdminPhp\Actions\BuildPreview;
use AdminPhp\Tests\Fixtures\Article;
use AdminPhp\Tests\Fixtures\ArticleModule;
use Illuminate\Support\Facades\Blade;

function articleWithBlocks(): Article
{
    $article = Article::query()->forceCreate(['title' => ['fr' => 'Rénover']]);

    $article->blocks()->createMany([
        ['editor_name' => 'content', 'type' => 'quote', 'position' => 1, 'content' => ['quote' => ['fr' => 'Deuxième']]],
        ['editor_name' => 'content', 'type' => 'text', 'position' => 0, 'content' => ['body' => ['fr' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Premier']]]]]]]],
        ['editor_name' => 'sidebar', 'type' => 'quote', 'position' => 0, 'content' => ['quote' => ['fr' => 'À côté']]],
    ]);

    return $article->fresh();
}

it('renders every block with the view of its type, in their order', function () {
    $article = articleWithBlocks();

    expect((string) $article->renderBlocks('content'))->toBe(
        '<div class="text"><p>Premier</p></div>'."\n"
        .'<blockquote data-item="'.$article->id.'">Deuxième</blockquote>'."\n"
    );
});

it('renders the blocks of every field, or of the one given', function () {
    $article = articleWithBlocks();

    expect((string) $article->renderBlocks())->toContain('Premier', 'Deuxième', 'À côté')
        ->and((string) $article->renderBlocks('sidebar'))->toBe('<blockquote data-item="'.$article->id.'">À côté</blockquote>'."\n");
});

it('renders a block type with another view when asked', function () {
    expect((string) articleWithBlocks()->renderBlocks('sidebar', ['quote' => 'blocks.quote-small']))->toBe('<q>À côté</q>'."\n");
});

it('takes the folder of the views from the config', function () {
    config(['adminphp.blocks.views' => 'site.blocks', 'app.debug' => true]);

    expect((string) articleWithBlocks()->renderBlocks('sidebar'))->toContain('resources/views/site/blocks/quote.blade.php');
});

it('says which view to create while debugging, and renders nothing in production', function () {
    $article = Article::query()->forceCreate(['title' => ['fr' => 'Titre']]);
    $article->blocks()->create(['editor_name' => 'content', 'type' => 'gallery', 'position' => 0, 'content' => []]);

    config(['app.debug' => true]);
    expect((string) $article->fresh()->renderBlocks())->toContain('Bloc « gallery » sans vue', 'resources/views/blocks/gallery.blade.php');

    config(['app.debug' => false]);
    expect((string) $article->fresh()->renderBlocks())->toBe('');
});

it('renders the unsaved blocks of a preview', function () {
    $preview = app(BuildPreview::class)->handle(new ArticleModule, [
        'title' => ['fr' => 'Aperçu'],
        'content' => [['type' => 'quote', 'content' => ['quote' => ['fr' => 'Pas encore enregistrée']]]],
    ]);

    expect((string) $preview->renderBlocks('content'))->toContain('Pas encore enregistrée');
});

it('is not escaped in a Blade echo', function () {
    expect(Blade::render('{{ $article->renderBlocks("sidebar") }}', ['article' => articleWithBlocks()]))->toContain('<blockquote');
});
