<?php

namespace App\Services\Operations;

use App\Enums\AccountStatus;
use App\Enums\BackupKind;
use App\Enums\BackupStatus;
use App\Enums\CertificationStatus;
use App\Enums\NotificationType;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Enums\Visibility;
use App\Models\BackupRun;
use App\Models\Certification;
use App\Models\Module;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ModuleNotification;
use Illuminate\Support\Carbon;

/**
 * Rapport du lundi (F-ADM-10, W08.6) : semaine écoulée (lundi à dimanche), disponibilité « non
 * mesurée » jusqu'à la MEP (décision 4a), sauvegardes, dernier test de restauration, signalements
 * ouverts, certifications en attente, nouveaux comptes et modules publics. Envoyé même sans activité.
 */
class WeeklyReport
{
    /** @return array<string, string|int> */
    public function build(?Carbon $now = null): array
    {
        $now ??= now();
        $from = $now->copy()->subWeek()->startOfWeek();
        $to = $from->copy()->endOfWeek();
        $runs = BackupRun::query()->whereIn('kind', [BackupKind::Daily, BackupKind::Monthly])->whereBetween('started_at', [$from, $to]);
        $restore = BackupRun::query()->where('kind', BackupKind::RestoreTest)->where('status', BackupStatus::Success)->latest('started_at')->first();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'backups_ok' => (clone $runs)->where('status', BackupStatus::Success)->count(),
            'backups_failed' => (clone $runs)->where('status', BackupStatus::Failed)->count(),
            'restore' => $restore?->started_at->toDateString() ?? '',
            'reports_open' => Report::query()->whereIn('status', ReportStatus::open())->count(),
            'reports_stale' => Report::query()->whereIn('status', ReportStatus::open())->where('created_at', '<=', $now->copy()->subHours((int) config('koryhub.moderation.stale_hours', 48)))->count(),
            'certifications' => Certification::query()->whereIn('status', CertificationStatus::open())->count(),
            'accounts' => User::query()->whereBetween('created_at', [$from, $to])->count(),
            'modules' => Module::query()->where('visibility', Visibility::Public)->whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    /** Envoie le rapport à chaque admin actif ; renvoie le nombre de destinataires. */
    public function send(): int
    {
        $params = $this->build();
        $count = 0;
        User::query()->where('role', Role::Admin)->where('status', AccountStatus::Active)->each(function (User $admin) use ($params, &$count) {
            $admin->notify(new ModuleNotification(NotificationType::AdminWeeklyReport, $params));
            $count++;
        });

        return $count;
    }
}
