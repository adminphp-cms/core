<?php

namespace Gmarineau\AdminPhp\Tests\Fixtures;

use Gmarineau\AdminPhp\Fields\Browser;
use Gmarineau\AdminPhp\Fields\Text;
use Gmarineau\AdminPhp\Modules\Module;

/**
 * Links of a two-level site menu, ordered by hand.
 */
class MenuLinkModule extends Module
{
    protected string $model = MenuLink::class;

    protected int $nestingDepth = 1;

    public function form(): array
    {
        return [
            Text::make('title')->label('Titre')->translatable()->required(),
            Browser::make('target')->label('Lien vers')->modules([ArticleModule::class])->max(1),
        ];
    }
}
