<?php

use AdminPhp\Actions\GetFormData;
use AdminPhp\Actions\SaveModuleContent;
use AdminPhp\Fields\Date;
use AdminPhp\Fields\Select;
use AdminPhp\Fields\Toggle;
use AdminPhp\Tests\Fixtures\Article;
use AdminPhp\Tests\Fixtures\ArticleModule;
use AdminPhp\Tests\Fixtures\Category;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $input
 */
function saveArticleWith(array $input, ?Article $article = null): Article
{
    return app(SaveModuleContent::class)->handle(new ArticleModule, ['title' => ['fr' => 'Titre'], ...$input], $article);
}

/**
 * @param  array<string, mixed>  $input
 * @return array<string, array<int, string>>
 */
function validationErrorsFor(array $input): array
{
    try {
        saveArticleWith($input);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('describes the new fields for the admin front', function () {
    $fields = collect((new ArticleModule)->toSchema()['fields'])->keyBy('name');

    expect($fields['category'])->toMatchArray([
        'type' => 'select',
        'multiple' => false,
        'options' => [
            ['value' => 'renovation', 'label' => 'Rénovation'],
            ['value' => 'garden', 'label' => 'Jardin'],
            ['value' => 'new-build', 'label' => 'Neuf'],
        ],
    ])
        ->and($fields['tags'])->toMatchArray(['type' => 'select', 'multiple' => true])
        ->and($fields['tags']['options'][0])->toBe(['value' => 1, 'label' => 'Bois'])
        ->and($fields['is_featured']['type'])->toBe('toggle')
        ->and($fields['event_on'])->toMatchArray(['type' => 'date', 'withTime' => false])
        ->and($fields['event_at'])->toMatchArray(['type' => 'date', 'withTime' => true]);
});

it('stores and reads back choices, flags and dates', function () {
    $article = saveArticleWith([
        'category' => 'garden',
        'tags' => ['3', '1'],
        'is_featured' => true,
        'event_on' => '2026-12-05',
        'event_at' => '2026-12-05T18:30:00+00:00',
    ]);

    $article = $article->fresh();

    expect($article->category)->toBe(Category::Garden)
        ->and($article->tags)->toBe([3, 1])
        ->and($article->is_featured)->toBeTrue()
        ->and($article->event_on->format('Y-m-d'))->toBe('2026-12-05')
        ->and($article->event_at->utc()->format('Y-m-d H:i'))->toBe('2026-12-05 18:30');

    expect(app(GetFormData::class)->handle(new ArticleModule, $article))->toMatchArray([
        'category' => 'garden',
        'tags' => [3, 1],
        'is_featured' => true,
        'event_on' => '2026-12-05',
        'event_at' => $article->event_at->toIso8601String(),
    ]);
});

it('clears optional values and keeps toggles as booleans', function () {
    $article = saveArticleWith(['category' => 'garden', 'tags' => [1], 'is_featured' => true, 'event_on' => '2026-12-05']);

    saveArticleWith(['category' => null, 'tags' => [], 'is_featured' => null, 'event_on' => null], $article);

    $article = $article->fresh();

    expect($article->category)->toBeNull()
        ->and($article->tags)->toBe([])
        ->and($article->is_featured)->toBeFalse()
        ->and($article->event_on)->toBeNull();
});

it('rejects unknown options, repeated tags and malformed dates', function () {
    expect(validationErrorsFor([
        'category' => 'kitchen',
        'tags' => [1, 1, 9],
        'is_featured' => 'maybe',
        'event_on' => '05/12/2026',
        'event_at' => 'not a date',
    ]))->toHaveKeys(['category', 'tags.0', 'tags.2', 'is_featured', 'event_on', 'event_at']);
});

it('validates dates and toggles inside blocks too', function () {
    $field = Date::make('starts_on')->required();

    expect($field->validationRules('content.0.content.starts_on', null))
        ->toBe(['content.0.content.starts_on' => ['required', 'date_format:Y-m-d']])
        ->and(Toggle::make('is_open')->dehydrate('1'))->toBeTrue()
        ->and(Toggle::make('is_open')->dehydrate(null))->toBeFalse();
});

it('cannot make a choice, a flag or a date translatable', function (string $fieldClass) {
    $fieldClass::make('value')->translatable();
})->with([Select::class, Toggle::class, Date::class])->throws(LogicException::class);

it('only accepts a backed enum class as options', function () {
    Select::make('category')->options(Article::class);
})->throws(InvalidArgumentException::class);
