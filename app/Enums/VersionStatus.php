<?php

namespace App\Enums;

/**
 * Statut d'une version (BACKEND-SCHEMA §6, §7) : envoyée → publiée ou rejetée ; publiée →
 * dépréciée (et retour) ; publiée ou dépréciée → retirée (W07). Une version rejetée ne devient
 * jamais publiée : elle est renvoyée comme une nouvelle ligne sous le même numéro.
 */
enum VersionStatus: string implements HasStatusBadge
{
    case Submitted = 'submitted';
    case Published = 'published';
    case Rejected = 'rejected';
    case Deprecated = 'deprecated';
    case Removed = 'removed';

    public function label(): string
    {
        return __("modules.version_status.{$this->value}");
    }

    public function color(): StatusColor
    {
        return match ($this) {
            self::Submitted => StatusColor::Info,
            self::Published => StatusColor::Success,
            self::Rejected, self::Removed => StatusColor::Danger,
            self::Deprecated => StatusColor::Warning,
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Submitted => [self::Published, self::Rejected],
            self::Published => [self::Deprecated, self::Removed],
            self::Deprecated => [self::Published, self::Removed],
            default => [],
        };
    }

    /** Version installable et comptée dans la suite des numéros. */
    public function isAvailable(): bool
    {
        return $this === self::Published || $this === self::Deprecated;
    }
}
