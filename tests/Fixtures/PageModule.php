<?php

namespace AdminPhp\Tests\Fixtures;

use AdminPhp\Fields\Text;
use AdminPhp\Modules\Module;

/**
 * Pages nested three levels deep, with a trash.
 */
class PageModule extends Module
{
    protected string $model = Page::class;

    protected int $nestingDepth = 2;

    public function form(): array
    {
        return [
            Text::make('title')->label('Titre')->translatable()->required(),
        ];
    }
}
