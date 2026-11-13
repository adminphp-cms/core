<?php

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Fields\Field;
use Gmarineau\AdminPhp\Fields\Select;
use Gmarineau\AdminPhp\Fields\Text;
use Gmarineau\AdminPhp\Fields\Toggle;
use Gmarineau\AdminPhp\Models\Setting;
use Gmarineau\AdminPhp\Settings\Settings;
use Illuminate\Support\Facades\Validator;

class DefaultsSettings extends Settings
{
    public function form(): array
    {
        return [
            Text::make('site_name')->default('Mon site'),
            Text::make('tagline')->translatable()->default('Bienvenue'),
            Toggle::make('maintenance')->default(true),
            Text::make('contact_email')->email(),
        ];
    }
}

/**
 * @return array<string, array<int, string>>
 */
function fieldErrors(Field $field, mixed $value): array
{
    return Validator::make([$field->name => $value], $field->validationRules($field->name, $value))->errors()->toArray();
}

it('describes the help, the placeholder and the default of a field', function () {
    $schema = Text::make('phone')->label('Téléphone')->note('Affiché dans le pied de page.')->placeholder('+41 22 000 00 00')->default('+41')->toSchema();

    expect($schema)->toMatchArray([
        'note' => 'Affiché dans le pied de page.',
        'placeholder' => '+41 22 000 00 00',
        'default' => '+41',
    ])
        ->and(Select::make('category')->options(['a' => 'A'])->toSchema())->toMatchArray(['note' => null, 'placeholder' => null, 'default' => null]);
});

it('gives the default of a translatable field in the default locale', function () {
    expect(Text::make('title')->translatable()->default('Bonjour')->defaultValue())->toBe(['fr' => 'Bonjour'])
        ->and(Text::make('title')->translatable()->default(['fr' => 'Bonjour', 'en' => 'Hello'])->defaultValue())->toBe(['fr' => 'Bonjour', 'en' => 'Hello'])
        ->and(Text::make('title')->defaultValue())->toBeNull();
});

it('uses the defaults for the settings never saved, on the site and in the admin', function () {
    app(AdminPhp::class)->registerSettings(DefaultsSettings::class);
    Setting::query()->create(['key' => 'maintenance', 'value' => false]);
    $adminPhp = app(AdminPhp::class);

    expect($adminPhp->setting('site_name'))->toBe('Mon site')
        ->and($adminPhp->setting('tagline', locale: 'en'))->toBe('Bienvenue')
        ->and($adminPhp->setting('maintenance'))->toBeFalse()
        ->and($adminPhp->setting('contact_email', 'contact@example.com'))->toBe('contact@example.com');

    actingAsAdmin();

    $this->getJson('/admin/api/settings')
        ->assertOk()
        ->assertJsonPath('data.values.site_name', 'Mon site')
        ->assertJsonPath('data.values.tagline', ['fr' => 'Bienvenue'])
        ->assertJsonPath('data.values.maintenance', false)
        ->assertJsonPath('data.schema.fields.3.inputType', 'email');
});

it('describes the kind of a text for the admin front', function (Text $field, array $expected) {
    expect($field->toSchema())->toMatchArray($expected);
})->with([
    'text' => [Text::make('a'), ['inputType' => 'text', 'min' => null, 'max' => null, 'step' => null]],
    'email' => [Text::make('a')->email(), ['inputType' => 'email']],
    'url' => [Text::make('a')->url(), ['inputType' => 'url']],
    'tel' => [Text::make('a')->tel(), ['inputType' => 'tel']],
    'number' => [Text::make('a')->number(min: 0, max: 10, step: 1), ['inputType' => 'number', 'min' => 0, 'max' => 10, 'step' => 1]],
]);

it('validates an e-mail address', function () {
    $field = Text::make('email')->email();

    expect(fieldErrors($field, 'hello@example.com'))->toBe([])
        ->and(fieldErrors($field, 'hello'))->toHaveKey('email');
});

it('validates a web address', function (string $url, bool $isValid) {
    expect(fieldErrors(Text::make('website')->url(), $url) === [])->toBe($isValid);
})->with([
    ['https://example.com/page', true],
    ['http://example.com', true],
    ['example.com', false],
    ['javascript:alert(1)', false],
    ['ftp://example.com', false],
]);

it('validates a number and its bounds', function () {
    $whole = Text::make('rooms')->number(min: 1, max: 20, step: 1);
    $decimal = Text::make('price')->number(min: 0);

    expect(fieldErrors($whole, '4'))->toBe([])
        ->and(fieldErrors($whole, '4.5'))->toHaveKey('rooms')
        ->and(fieldErrors($whole, '0'))->toHaveKey('rooms')
        ->and(fieldErrors($whole, '21'))->toHaveKey('rooms')
        ->and(fieldErrors($whole, 'quatre'))->toHaveKey('rooms')
        ->and(fieldErrors($decimal, '12.50'))->toBe([])
        ->and(fieldErrors($decimal, '-1'))->toHaveKey('price');
});

it('validates every locale of a translatable typed text', function () {
    $field = Text::make('link')->url()->translatable();

    expect(fieldErrors($field, ['fr' => 'https://example.com/fr', 'en' => 'pas une adresse']))->toHaveKey('link.en');
});

it('stores a number as a number and trims the other kinds', function () {
    expect(Text::make('rooms')->number(step: 1)->dehydrate(' 4 '))->toBe(4)
        ->and(Text::make('price')->number()->dehydrate('12.50'))->toBe(12.5)
        ->and(Text::make('price')->number()->dehydrate(''))->toBeNull()
        ->and(Text::make('email')->email()->dehydrate(' hello@example.com '))->toBe('hello@example.com');
});

it('keeps the custom rules after the rules of the kind', function () {
    $field = Text::make('email')->email()->rules(['ends_with:@example.com']);

    expect(fieldErrors($field, 'hello@other.com'))->toHaveKey('email')
        ->and(fieldErrors($field, 'hello@example.com'))->toBe([]);
});

it('refuses a multiline text of another kind', function (Closure $make) {
    $make();
})->with([
    'multiline then email' => [fn () => Text::make('a')->multiline()->email()],
    'number then multiline' => [fn () => Text::make('a')->number()->multiline()],
])->throws(LogicException::class);
