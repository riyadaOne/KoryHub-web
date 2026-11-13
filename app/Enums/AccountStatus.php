<?php

namespace App\Enums;

/**
 * Statut d'un compte (BACKEND-SCHEMA §6, §7) : Actif → Suspendu (admin, réversible, W07)
 * → Supprimé (RGPD ou admin). Un compte supprimé garde sa ligne pour réserver son nom @auteur.
 */
enum AccountStatus: string implements HasStatusBadge
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    public function label(): string
    {
        return __("account.status.{$this->value}");
    }

    public function color(): StatusColor
    {
        return match ($this) {
            self::Active => StatusColor::Success,
            self::Suspended => StatusColor::Danger,
            self::Deleted => StatusColor::Neutral,
        };
    }
}
