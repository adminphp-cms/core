<?php

namespace Gmarineau\AdminPhp\Enums;

/**
 * What an admin user may do: admins also manage the other admin users,
 * editors work on content only.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Editor => 'Éditeur',
        };
    }
}
