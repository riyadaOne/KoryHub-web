<?php

namespace App\Console\Commands;

use App\Enums\BackupStatus;
use App\Services\Operations\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Test de restauration (F-ADM-08, décision W08 2a) : la dernière sauvegarde réussie est chargée
 * dans BACKUP_RESTORE_DATABASE (jamais la base de l'application), puis ses tables sont comparées.
 * Chaque mois, le 2 à 4 h (routes/console.php) ; résultat dans S-72 et le rapport du lundi.
 */
#[Signature('koryhub:backup-restore-test')]
#[Description('Restaure la dernière sauvegarde dans la base de test et la vérifie')]
class TestRestore extends Command
{
    public function handle(BackupService $backups): int
    {
        $run = $backups->restoreTest();
        $run->status === BackupStatus::Success ? $this->info("Restauration réussie : {$run->note}") : $this->error("Restauration échouée : {$run->note}");

        return $run->status === BackupStatus::Success ? self::SUCCESS : self::FAILURE;
    }
}
