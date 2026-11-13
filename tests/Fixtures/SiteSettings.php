<?php

namespace AdminPhp\Tests\Fixtures;

use AdminPhp\Fields\Image;
use AdminPhp\Fields\Select;
use AdminPhp\Fields\Text;
use AdminPhp\Fields\Toggle;
use AdminPhp\Settings\Settings;

class SiteSettings extends Settings
{
    protected string $description = 'Réglages du site.';

    public function form(): array
    {
        return [
            Text::make('site_name')->label('Nom du site')->required(),
            Text::make('tagline')->label('Slogan')->translatable(),
            Select::make('homepage')->label('Page d’accueil')->options(
                fn (): array => Article::query()->get()->mapWithKeys(fn (Article $article): array => [$article->id => $article->getTranslation('title', 'fr')])->all(),
            ),
            Toggle::make('maintenance')->label('Maintenance'),
            Image::make('logo')->label('Logo'),
        ];
    }
}
