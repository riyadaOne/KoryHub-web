<?php

namespace App\Enums;

/**
 * Statut d'un module (BACKEND-SCHEMA §6, §7) : actif ou archivé. Un module archivé reste
 * visible et téléchargeable mais n'accepte plus de nouvelle version (F-REG-16).
 */
enum ModuleStatus: string implements HasStatusBadge
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return __("modules.status.{$this->value}");
    }

    public function color(): StatusColor
    {
        return match ($this) {
            self::Active => StatusColor::Success,
            self::Archived => StatusColor::Neutral,
        };
    }
}
