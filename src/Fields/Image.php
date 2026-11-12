<?php

namespace Gmarineau\AdminPhp\Fields;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Fields\Concerns\IsNotTranslatable;
use Illuminate\Validation\Rule;

/**
 * An image of the media library, stored as the id of its asset (a ULID).
 * Give the image its alternative text and caption with fields next to it,
 * so they can be translated.
 */
class Image extends Field
{
    use IsNotTranslatable;

    public function type(): string
    {
        return 'image';
    }

    protected function valueRules(): array
    {
        return ['string', 'ulid', Rule::exists(app(AdminPhp::class)->table('assets'), 'id'), ...$this->rules];
    }

    public function dehydrate(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}
