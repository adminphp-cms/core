<?php

namespace Gmarineau\AdminPhp\Tests\Fixtures;

use Gmarineau\AdminPhp\Fields\Select;
use Gmarineau\AdminPhp\Fields\Text;
use Gmarineau\AdminPhp\Fields\Toggle;
use Gmarineau\AdminPhp\Settings\Settings;

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
        ];
    }
}
