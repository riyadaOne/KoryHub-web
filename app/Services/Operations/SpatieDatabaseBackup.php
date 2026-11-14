<?php

namespace App\Services\Operations;

use App\Enums\BackupKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use ZipArchive;

/**
 * Sauvegarde par spatie/laravel-backup (W08.5, décision 1a) : mysqldump de la connexion des
 * migrations, zip chiffré en AES-256 (BACKUP_ARCHIVE_PASSWORD) sur le disque « backups ».
 * Restauration (décision 2a) : zip ouvert avec le mot de passe, dump chargé par le client mysql
 * dans une base séparée, jamais la base de l'application.
 */
class SpatieDatabaseBackup implements DatabaseBackup
{
    public function dump(BackupKind $kind): array
    {
        // Le paquet ajoute lui-même le préfixe (backup.destination.filename_prefix) au nom du fichier.
        $config = app(Config::class);
        $filename = $kind->value.'-'.now()->format('Y-m-d-His').'.zip';

        BackupJobFactory::createFromConfig($config)
            ->dontBackupFilesystem()
            ->onlyBackupTo('backups')
            ->setFilename($filename)
            ->disableSignals()
            ->run();

        $location = config('backup.backup.name').'/'.$config->backup->destination->filenamePrefix.$filename;

        return ['location' => $location, 'size' => Storage::disk('backups')->size($location)];
    }

    public function restore(string $location, string $database): array
    {
        $connection = config('database.connections.mysql_migrate');
        if ($database === '' || $database === $connection['database']) {
            throw new RuntimeException('Restauration refusée : base cible invalide.');
        }

        $directory = storage_path('app/backup-temp/restore');
        File::ensureDirectoryExists($directory);
        $dump = $this->extractDump(Storage::disk('backups')->path($location), $directory);

        try {
            $binary = rtrim((string) ($connection['dump']['dump_binary_path'] ?? ''), '/\\');
            $result = Process::env(['MYSQL_PWD' => (string) $connection['password']])->timeout(1800)->run([
                $binary === '' ? 'mysql' : $binary.DIRECTORY_SEPARATOR.'mysql',
                '--host='.$connection['host'], '--port='.$connection['port'], '--user='.$connection['username'],
                '--default-character-set=utf8mb4', $database,
                '--execute=source '.str_replace('\\', '/', $dump),
            ]);
            if ($result->failed()) {
                throw new RuntimeException(trim($result->errorOutput()) ?: 'mysql a échoué.');
            }
        } finally {
            File::delete($dump);
        }

        return $this->compare($connection['database'], $database);
    }

    public function delete(string $location): void
    {
        Storage::disk('backups')->delete($location);
    }

    private function extractDump(string $zipPath, string $directory): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Sauvegarde illisible : '.basename($zipPath));
        }
        $zip->setPassword((string) config('backup.backup.password'));

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_ends_with($name, '.sql')) {
                    $content = $zip->getFromIndex($i);
                    if ($content === false) {
                        throw new RuntimeException('Mot de passe de la sauvegarde incorrect.');
                    }
                    $target = $directory.DIRECTORY_SEPARATOR.'restore.sql';
                    File::put($target, $content);

                    return $target;
                }
            }
        } finally {
            $zip->close();
        }

        throw new RuntimeException('Aucun fichier SQL dans la sauvegarde.');
    }

    /**
     * Toutes les tables de la base de l'application existent dans la base restaurée.
     *
     * @return array{tables: int, rows: int}
     */
    private function compare(string $source, string $restored): array
    {
        $db = DB::connection('mysql_migrate');
        $tables = fn (string $schema) => collect($db->select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [$schema, 'BASE TABLE']))->pluck('name');

        $missing = $tables($source)->diff($tables($restored));
        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Tables absentes de la restauration : '.$missing->implode(', '));
        }

        $restoredTables = $tables($restored);
        $rows = $restoredTables->sum(fn (string $table) => (int) $db->selectOne('SELECT COUNT(*) AS total FROM `'.str_replace('`', '', $restored).'`.`'.str_replace('`', '', $table).'`')->total);

        return ['tables' => $restoredTables->count(), 'rows' => $rows];
    }
}
