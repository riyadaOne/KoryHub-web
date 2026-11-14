<?php

namespace App\Services\Operations;

use App\Enums\BackupKind;

/**
 * Sauvegarde et restauration de la base (W08.5) : implémentée par SpatieDatabaseBackup
 * (spatie/laravel-backup, mysqldump, zip chiffré) ; remplacée par un faux dans les tests.
 */
interface DatabaseBackup
{
    /**
     * Crée une sauvegarde chiffrée sur le disque « backups » ; lève une exception en cas d'échec.
     *
     * @return array{location: string, size: int}
     */
    public function dump(BackupKind $kind): array;

    /**
     * Restaure la sauvegarde dans une autre base et vérifie qu'elle contient toutes les tables.
     *
     * @return array{tables: int, rows: int}
     */
    public function restore(string $location, string $database): array;

    /** Efface le fichier d'une sauvegarde (conservation). */
    public function delete(string $location): void;
}
