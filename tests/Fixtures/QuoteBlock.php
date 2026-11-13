<?php

namespace AdminPhp\Tests\Fixtures;

use AdminPhp\Blocks\Block;
use AdminPhp\Fields\Text;

class QuoteBlock extends Block
{
    protected string $title = 'Citation';

    protected int $version = 2;

    public function form(): array
    {
        return [
            Text::make('quote')->label('Citation')->translatable()->multiline()->required(),
            Text::make('author')->label('Auteur'),
        ];
    }
}
