<?php

namespace App\Console\Commands;

use App\Enums\BackupKind;
use App\Enums\BackupStatus;
use App\Services\Operations\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sauvegarde de la base (F-ADM-08) : quotidienne à 2 h, mensuelle le 1er à 3 h (routes/console.php).
 * Notée dans backup_runs ; en échec, les admins sont alertés (admin_incident).
 */
#[Signature('koryhub:backup {--kind=daily : daily ou monthly}')]
#[Description('Sauvegarde chiffrée de la base (quotidienne ou mensuelle)')]
class RunBackup extends Command
{
    public function handle(BackupService $backups): int
    {
        $kind = BackupKind::tryFrom((string) $this->option('kind'));
        if (! in_array($kind, [BackupKind::Daily, BackupKind::Monthly], true)) {
            $this->error('--kind : daily ou monthly.');

            return self::INVALID;
        }

        $run = $backups->run($kind);
        $run->status === BackupStatus::Success
            ? $this->info("Sauvegarde réussie : {$run->location} ({$run->size} octets).")
            : $this->error("Sauvegarde échouée : {$run->note}");

        return $run->status === BackupStatus::Success ? self::SUCCESS : self::FAILURE;
    }
}
