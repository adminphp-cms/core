<?php

namespace Gmarineau\AdminPhp\Tests\Fixtures;

use Gmarineau\AdminPhp\Fields\Blocks;
use Gmarineau\AdminPhp\Fields\Browser;
use Gmarineau\AdminPhp\Fields\Date;
use Gmarineau\AdminPhp\Fields\Image;
use Gmarineau\AdminPhp\Fields\Repeater;
use Gmarineau\AdminPhp\Fields\Select;
use Gmarineau\AdminPhp\Fields\Slug;
use Gmarineau\AdminPhp\Fields\Text;
use Gmarineau\AdminPhp\Fields\Toggle;
use Gmarineau\AdminPhp\Modules\Column;
use Gmarineau\AdminPhp\Modules\Filter;
use Gmarineau\AdminPhp\Modules\Module;

class ArticleModule extends Module
{
    protected string $model = Article::class;

    protected string $description = 'Actualités et récits de chantier.';

    protected string $icon = 'newspaper';

    protected ?string $previewView = 'article-preview';

    public function columns(): array
    {
        return [
            Column::make('cover'),
            Column::make('title')->sortable(),
            Column::status(),
            Column::make('category')->sortable(),
            Column::make('tags'),
            Column::make('is_featured')->label('Une'),
            Column::make('event_on'),
            Column::make('updated_at')->label('Modifié')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::make('category'),
            Filter::make('tags'),
            Filter::make('is_featured')->label('À la une'),
        ];
    }

    public function form(): array
    {
        return [
            Text::make('title')->label('Titre')->translatable()->required(),
            Text::make('lead')->label('Chapeau')->translatable()->multiline(),
            Blocks::make('content')->label('Contenu')->allow([TextBlock::class, QuoteBlock::class, ImageBlock::class, LinksBlock::class]),
            Select::make('category')->label('Catégorie')->options(Category::class),
            Select::make('tags')->label('Étiquettes')->options([1 => 'Bois', 2 => 'Pierre', 3 => 'Isolation'])->multiple(),
            Toggle::make('is_featured')->label('À la une'),
            Date::make('event_on')->label('Date de l’événement'),
            Date::make('event_at')->label('Début')->withTime(),
            Slug::make('slug')->label('Adresse')->from('title')->translatable()->prefix('/articles/'),
            Repeater::make('faq')->label('FAQ')->max(3)->fields([
                Text::make('question')->label('Question')->translatable()->required(),
                Text::make('answer')->label('Réponse')->translatable()->multiline(),
            ]),
            Image::make('cover')->label('Couverture'),
            Browser::make('related_articles')->label('Articles liés')->modules([ArticleModule::class, TagModule::class])->max(2),
        ];
    }
}
