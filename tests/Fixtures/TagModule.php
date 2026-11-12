<?php

namespace Gmarineau\AdminPhp\Tests\Fixtures;

use Gmarineau\AdminPhp\Fields\Slug;
use Gmarineau\AdminPhp\Fields\Text;
use Gmarineau\AdminPhp\Modules\Module;

/**
 * A module with a plain (untranslated) slug made from a field other than "title".
 */
class TagModule extends Module
{
    protected string $model = Tag::class;

    protected string $titleField = 'name';

    public function form(): array
    {
        return [
            Text::make('name')->required(),
            Slug::make('slug')->from('name')->maxLength(20),
        ];
    }
}
