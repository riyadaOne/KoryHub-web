<?php

namespace App\Enums;

/**
 * Visibilité d'un module (F-REG-13, BACKEND-SCHEMA §7) : un module privé n'est visible que par
 * son auteur et tient lieu de brouillon ; tout autre reçoit 404.
 */
enum Visibility: string implements HasStatusBadge
{
    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return __("modules.visibility.{$this->value}");
    }

    public function color(): StatusColor
    {
        return match ($this) {
            self::Public => StatusColor::Success,
            self::Private => StatusColor::Info,
        };
    }
}
