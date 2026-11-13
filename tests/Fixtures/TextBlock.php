<?php

namespace AdminPhp\Tests\Fixtures;

use AdminPhp\Blocks\Block;
use AdminPhp\Fields\RichText;

class TextBlock extends Block
{
    protected string $title = 'Paragraphe';

    public function form(): array
    {
        return [
            RichText::make('body')->label('Texte')->translatable()->required(),
        ];
    }
}
