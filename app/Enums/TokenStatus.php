<?php

namespace App\Enums;

/**
 * Statut d'un jeton du CLI (BACKEND-SCHEMA §6, §7), calculé à partir des dates, jamais stocké :
 * révoqué si revoked_at est posé, sinon expiré si expires_at est passé, sinon actif.
 * « Expire bientôt » (7 jours ou moins) est un actif signalé sur S-41 (maquettes W02).
 */
enum TokenStatus: string implements HasStatusBadge
{
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return __("tokens.status.{$this->value}");
    }

    public function color(): StatusColor
    {
        return match ($this) {
            self::Active => StatusColor::Success,
            self::ExpiringSoon => StatusColor::Warning,
            self::Expired, self::Revoked => StatusColor::Neutral,
        };
    }

    /** Un jeton actif ou qui expire bientôt peut encore servir et être révoqué. */
    public function isUsable(): bool
    {
        return $this === self::Active || $this === self::ExpiringSoon;
    }
}
