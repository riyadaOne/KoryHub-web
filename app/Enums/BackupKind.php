<?php

namespace App\Enums;

/** Type d'une sauvegarde (BACKEND-SCHEMA §7, F-ADM-08) : quotidienne (30 jours), mensuelle (12 mois), test de restauration. */
enum BackupKind: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case RestoreTest = 'restore_test';

    public function label(): string
    {
        return __("admin.health.kind.{$this->value}");
    }

    /** Sauvegardes gardées sur le disque ; les plus anciennes sont effacées (TRD §25.3). */
    public function retention(): ?int
    {
        return match ($this) {
            self::Daily => 30,
            self::Monthly => 12,
            self::RestoreTest => null,
        };
    }
}
