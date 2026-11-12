<?php

namespace Gmarineau\AdminPhp\Tests\Fixtures;

use Gmarineau\AdminPhp\Blocks\Block;
use Gmarineau\AdminPhp\Fields\Browser;
use Gmarineau\AdminPhp\Fields\Repeater;
use Gmarineau\AdminPhp\Fields\Text;

/**
 * A block mixing chosen articles and a list of free links.
 */
class LinksBlock extends Block
{
    protected string $title = 'Liens';

    public function form(): array
    {
        return [
            Browser::make('articles')->label('Articles')->modules([ArticleModule::class]),
            Repeater::make('links')->label('Liens')->fields([
                Text::make('label')->label('Texte')->translatable()->required(),
                Text::make('url')->label('Adresse')->required(),
            ]),
        ];
    }
}
