<?php

namespace AdminPhp\Tests\Fixtures;

use AdminPhp\Blocks\Block;
use AdminPhp\Fields\Image;
use AdminPhp\Fields\Text;

class ImageBlock extends Block
{
    protected string $title = 'Image';

    public function form(): array
    {
        return [
            Image::make('image')->label('Image')->required(),
            Text::make('alt')->label('Texte alternatif')->translatable(),
            Text::make('caption')->label('Légende')->translatable(),
        ];
    }
}
