<?php

namespace Gmarineau\AdminPhp\Tests\Fixtures;

use Gmarineau\AdminPhp\Fields\Text;
use Gmarineau\AdminPhp\Modules\Module;

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
