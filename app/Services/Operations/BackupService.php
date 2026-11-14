<?php

namespace App\Services\Operations;

use App\Enums\AccountStatus;
use App\Enums\BackupKind;
use App\Enums\BackupStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\ModuleNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sauvegardes et supervision (W08.5, F-ADM-08, F-ADM-09, T-36, T-37) : chaque sauvegarde et chaque
 * test de restauration est noté dans backup_runs ; conservation (30 quotidiennes, 12 mensuelles) ;
 * échec = alerte admin_incident et signal « fail » à Healthchecks.io ; signal de vie du planificateur.
 */
class BackupService
{
    public const HEARTBEAT_KEY = 'koryhub.heartbeat_at';

    public function __construct(private DatabaseBackup $backup) {}

    public function run(BackupKind $kind): BackupRun
    {
        $run = BackupRun::create(['kind' => $kind, 'status' => BackupStatus::Running, 'started_at' => now()]);

        try {
            ['location' => $location, 'size' => $size] = $this->backup->dump($kind);
            $run->finish(BackupStatus::Success, ['location' => $location, 'size' => $size]);
            $this->prune($kind);
            $this->ping('backup');
        } catch (Throwable $exception) {
            $this->fail($run, $exception);
        }

        return $run->fresh();
    }

    /** Restaure la dernière sauvegarde réussie dans la base de test (décision 2a). */
    public function restoreTest(): BackupRun
    {
        $run = BackupRun::create(['kind' => BackupKind::RestoreTest, 'status' => BackupStatus::Running, 'started_at' => now()]);
        $source = BackupRun::query()->whereIn('kind', [BackupKind::Daily, BackupKind::Monthly])
            ->where('status', BackupStatus::Success)->whereNotNull('location')->latest('started_at')->first();

        try {
            if ($source === null) {
                throw new \RuntimeException('Aucune sauvegarde réussie à restaurer.');
            }
            ['tables' => $tables, 'rows' => $rows] = $this->backup->restore($source->location, (string) config('koryhub.backups.restore_database'));
            $run->finish(BackupStatus::Success, ['location' => $source->location, 'note' => __('admin.health.restore_note', ['tables' => $tables, 'rows' => $rows])]);
            $this->ping('backup');
        } catch (Throwable $exception) {
            $this->fail($run, $exception);
        }

        return $run->fresh();
    }

    /** Signal de vie du planificateur, toutes les 5 minutes (TRD §25.4). */
    public function heartbeat(): void
    {
        Cache::forever(self::HEARTBEAT_KEY, now()->toIso8601String());
        $this->ping('heartbeat');
    }

    /** Efface les sauvegardes au-delà de la conservation ; la ligne reste dans backup_runs. */
    private function prune(BackupKind $kind): void
    {
        if ($kind->retention() === null) {
            return;
        }

        BackupRun::query()->where('kind', $kind)->where('status', BackupStatus::Success)->whereNotNull('location')
            ->latest('started_at')->skip($kind->retention())->take(PHP_INT_MAX)->get()
            ->each(function (BackupRun $old) {
                $this->backup->delete($old->location);
                $old->forceFill(['location' => null])->save();
            });
    }

    private function fail(BackupRun $run, Throwable $exception): void
    {
        $note = Str::limit(trim($exception->getMessage()), 500);
        $run->finish(BackupStatus::Failed, ['note' => $note]);
        $this->ping('backup', '/fail');

        User::query()->where('role', Role::Admin)->where('status', AccountStatus::Active)->each(
            fn (User $admin) => $admin->notify(new ModuleNotification(NotificationType::AdminIncident, ['backup_kind' => $run->kind->value, 'note' => $note]))
        );
    }

    /** Healthchecks.io (F-ADM-09) : rien n'est envoyé tant que l'adresse n'est pas dans .env ; jamais bloquant. */
    private function ping(string $check, string $suffix = ''): void
    {
        $url = (string) config("koryhub.healthchecks.{$check}");
        if ($url === '') {
            return;
        }

        try {
            Http::timeout(10)->get(rtrim($url, '/').$suffix);
        } catch (Throwable) {
            // Healthchecks.io injoignable : son propre délai d'absence de signal alertera.
        }
    }
}
