<?php

namespace Gmarineau\AdminPhp\Tests\Fixtures;

use Gmarineau\AdminPhp\Blocks\Block;
use Gmarineau\AdminPhp\Fields\Image;
use Gmarineau\AdminPhp\Fields\Text;

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
