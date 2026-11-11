<?php

namespace Gmarineau\AdminPhp\Tests\Fixtures;

use Gmarineau\AdminPhp\Fields\Blocks;
use Gmarineau\AdminPhp\Fields\Date;
use Gmarineau\AdminPhp\Fields\Select;
use Gmarineau\AdminPhp\Fields\Text;
use Gmarineau\AdminPhp\Fields\Toggle;
use Gmarineau\AdminPhp\Modules\Module;

class ArticleModule extends Module
{
    protected string $model = Article::class;

    protected string $description = 'Actualités et récits de chantier.';

    protected string $icon = 'newspaper';

    public function form(): array
    {
        return [
            Text::make('title')->label('Titre')->translatable()->required(),
            Text::make('lead')->label('Chapeau')->translatable()->multiline(),
            Blocks::make('content')->label('Contenu')->allow([TextBlock::class, QuoteBlock::class]),
            Select::make('category')->label('Catégorie')->options(Category::class),
            Select::make('tags')->label('Étiquettes')->options([1 => 'Bois', 2 => 'Pierre', 3 => 'Isolation'])->multiple(),
            Toggle::make('is_featured')->label('À la une'),
            Date::make('event_on')->label('Date de l’événement'),
            Date::make('event_at')->label('Début')->withTime(),
        ];
    }
}
