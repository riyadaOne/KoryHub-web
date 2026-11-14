<?php

namespace App\Enums;

/** Statut d'une sauvegarde (BACKEND-SCHEMA §6, §7) : en cours → réussie ou échouée ; jamais l'inverse. */
enum BackupStatus: string implements HasStatusBadge
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return __("admin.health.status.{$this->value}");
    }

    public function color(): StatusColor
    {
        return match ($this) {
            self::Running => StatusColor::Info,
            self::Success => StatusColor::Success,
            self::Failed => StatusColor::Danger,
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return $this === self::Running ? [self::Success, self::Failed] : [];
    }
}
