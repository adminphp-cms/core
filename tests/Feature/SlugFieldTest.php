<?php

use Gmarineau\AdminPhp\Actions\SaveModuleContent;
use Gmarineau\AdminPhp\Blocks\Block;
use Gmarineau\AdminPhp\Fields\Slug;
use Gmarineau\AdminPhp\Tests\Fixtures\Article;
use Gmarineau\AdminPhp\Tests\Fixtures\ArticleModule;
use Gmarineau\AdminPhp\Tests\Fixtures\Tag;
use Gmarineau\AdminPhp\Tests\Fixtures\TagModule;

/**
 * @param  array<string, mixed>  $input
 */
function saveTag(array $input, ?Tag $tag = null): Tag
{
    return app(SaveModuleContent::class)->handle(new TagModule, $input, $tag)->fresh();
}

it('generates the slug from its source field when it is left empty', function () {
    expect(saveTag(['name' => 'Rénovation & Jardin'])->slug)->toBe('renovation-jardin')
        ->and(saveTag(['name' => 'Été', 'slug' => ''])->slug)->toBe('ete');
});

it('normalizes a slug typed by hand', function () {
    expect(saveTag(['name' => 'Bois', 'slug' => '  Mon Slug À Moi !'])->slug)->toBe('mon-slug-a-moi');
});

it('adds a suffix to a slug already taken, within the maximum length', function () {
    saveTag(['name' => 'Bois']);

    expect(saveTag(['name' => 'Bois'])->slug)->toBe('bois-2')
        ->and(saveTag(['name' => 'bois', 'slug' => 'Bois'])->slug)->toBe('bois-3');

    saveTag(['name' => 'Une étiquette bien trop longue']);

    expect(saveTag(['name' => 'Une étiquette bien trop longue'])->slug)
        ->toBe('une-etiquette-bien-2')
        ->toHaveLength(20);
});

it('keeps the slug when the source changes later, and regenerates it once cleared', function () {
    $tag = saveTag(['name' => 'Bois']);

    $tag = saveTag(['name' => 'Pierre'], $tag);
    expect($tag->slug)->toBe('bois');

    $tag = saveTag(['name' => 'Pierre', 'slug' => ''], $tag);
    expect($tag->slug)->toBe('pierre');
});

it('does not suffix an unchanged slug against itself', function () {
    $tag = saveTag(['name' => 'Bois']);

    expect(saveTag(['name' => 'Bois massif', 'slug' => 'bois'], $tag)->slug)->toBe('bois');
});

it('generates one slug per locale from the translated title', function () {
    $article = app(SaveModuleContent::class)->handle(new ArticleModule, [
        'title' => ['fr' => 'À propos de nous', 'en' => 'About us'],
    ]);

    expect($article->fresh()->getTranslations('slug'))->toBe(['fr' => 'a-propos-de-nous', 'en' => 'about-us']);
});

it('only generates a translated slug where the title is translated, and keeps it unique per locale', function () {
    app(SaveModuleContent::class)->handle(new ArticleModule, ['title' => ['fr' => 'Contact', 'en' => 'Contact']]);

    $article = app(SaveModuleContent::class)->handle(new ArticleModule, [
        'title' => ['fr' => 'Contact'],
        'slug' => ['en' => 'Get in touch'],
    ]);

    expect($article->fresh()->getTranslations('slug'))->toBe(['fr' => 'contact-2', 'en' => 'get-in-touch']);
});

it('generates the slug of an item created through the admin API', function () {
    actingAsAdmin();

    $this->postJson('/admin/api/modules/article/items', ['title' => ['fr' => 'Premier article']])
        ->assertCreated()
        ->assertJsonPath('data.values.slug', ['fr' => 'premier-article']);
});

it('describes the slug for the admin front', function () {
    $schema = collect((new ArticleModule)->toSchema()['fields'])->firstWhere('name', 'slug');

    expect($schema)->toMatchArray([
        'type' => 'slug',
        'from' => 'title',
        'prefix' => '/articles/',
        'translatable' => true,
        'required' => false,
    ]);
});

it('never makes an empty slug fail validation, even when required', function () {
    expect(Slug::make('slug')->required()->validationRules('slug', null))->toBe(['slug' => ['nullable', 'string', 'max:255']]);
});

it('cannot be used inside a block', function () {
    $block = new class extends Block
    {
        public function form(): array
        {
            return [Slug::make('slug')];
        }
    };

    $block->fields();
})->throws(LogicException::class, 'Slug');

it('mirrors Laravel slugs in the admin front', function () {
    $examples = ['À propos', 'Rénovation & jardin', 'L’atelier, c’est nous !', 'snake_case et Espaces  multiples', 'contact@atelier', 'Œuvre – 2026'];

    expect(array_map(fn (string $text): string => (new Slug('slug'))->slugify($text), $examples))->toBe([
        'a-propos',
        'renovation-jardin',
        'latelier-cest-nous',
        'snake-case-et-espaces-multiples',
        'contact-at-atelier',
        'oeuvre-2026',
    ]);
});

it('is translatable on the article fixture', function () {
    expect((new Article)->isTranslatableAttribute('slug'))->toBeTrue();
});
