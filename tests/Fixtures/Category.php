<?php

namespace AdminPhp\Tests\Fixtures;

enum Category: string
{
    case Renovation = 'renovation';
    case Garden = 'garden';
    case NewBuild = 'new-build';

    public function label(): string
    {
        return match ($this) {
            self::Renovation => 'Rénovation',
            self::Garden => 'Jardin',
            self::NewBuild => 'Neuf',
        };
    }
}
