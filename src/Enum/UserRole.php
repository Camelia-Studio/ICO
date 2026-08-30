<?php

declare(strict_types=1);

namespace ICO\Enum;

enum UserRole: string
{
    case ADMINISTRATOR = 'administrator';
    case MODERATOR = 'moderator';
    case VISITOR = 'visitor';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrateur',
            self::MODERATOR => 'Modérateur',
            self::VISITOR => 'Visiteur',
        };
    }

    public function canAccessAdministration(): bool
    {
        return $this !== self::VISITOR;
    }
}
