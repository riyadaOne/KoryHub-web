<?php

namespace App\Services\Moderation;

use App\Enums\AccountStatus;
use App\Enums\NotificationType;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Enums\Role;
use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Signalements, partie développeur (W07.1, F-MOD-09, F-MOD-11, F-AUT-23, T-17) : un compte à
 * l'e-mail vérifié signale un module public, une version disponible, un compte actif ou un avis
 * visible, jamais le sien ; un seul signalement ouvert par compte et par objet. Accusé de
 * réception ; alerte unique des admins quand le seuil des signalements multiples est atteint.
 * La personne visée ne voit jamais qui l'a signalée.
 */
class ReportService
{
    public const MAX_DETAILS = 2000;

    public function __construct(private AuditLogger $audit) {}

    /** Objet signalable par ce compte, ou ModerationRefused. */
    public function target(ReportTargetType $type, string $targetId, User $reporter): Module|ModuleVersion|User|Review
    {
        $target = match ($type) {
            ReportTargetType::Module => Module::query()->where('visibility', Visibility::Public)->find($targetId),
            ReportTargetType::Version => ModuleVersion::query()
                ->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated])
                ->whereHas('module', fn ($q) => $q->where('visibility', Visibility::Public))
                ->find($targetId),
            ReportTargetType::User => User::query()->where('status', AccountStatus::Active)->find($targetId),
            ReportTargetType::Review => Review::query()->whereNull('hidden_at')
                ->whereHas('module', fn ($q) => $q->where('visibility', Visibility::Public))
                ->find($targetId),
        };

        if ($target === null) {
            throw new ModerationRefused('moderation.errors.target_not_found');
        }
        if ($this->ownerId($target) === $reporter->id) {
            throw new ModerationRefused('moderation.errors.own_target');
        }

        return $target;
    }

    /** Signalement encore ouvert de ce compte sur cet objet. */
    public function openReport(User $reporter, ReportTargetType $type, string $targetId): ?Report
    {
        return Report::query()->where('reporter_id', $reporter->id)->where('target_type', $type)
            ->where('target_id', $targetId)->whereIn('status', ReportStatus::open())->first();
    }

    public function create(User $reporter, ReportTargetType $type, string $targetId, ReportReason $reason, ?string $details): Report
    {
        if (! $reporter->hasVerifiedEmail()) {
            throw new ModerationRefused('moderation.errors.email_unverified');
        }
        $target = $this->target($type, $targetId, $reporter);
        if ($this->openReport($reporter, $type, $target->id) !== null) {
            throw new ModerationRefused('moderation.errors.duplicate');
        }

        $details = $details === null || trim($details) === '' ? null : mb_substr(trim($details), 0, self::MAX_DETAILS);

        $report = DB::transaction(function () use ($reporter, $type, $target, $reason, $details) {
            $report = Report::create([
                'reporter_id' => $reporter->id,
                'target_type' => $type,
                'target_id' => $target->id,
                'reason' => $reason,
                'details' => $details,
                'status' => ReportStatus::Open,
            ]);
            $this->audit->record('report.created', $report, [
                'status' => [null, ReportStatus::Open],
                'target_type' => [null, $type],
                'target_id' => [null, $target->id],
                'reason' => [null, $reason],
            ], actor: $reporter);

            return $report;
        });

        $reporter->notify(new ModuleNotification(NotificationType::ReportReceived, $this->params($report)));
        $this->alertIfMultiple($report);

        return $report;
    }

    /** Paramètres des notifications : objet visé (libellé court) et signalement. */
    public function params(Report $report): array
    {
        return ['target' => $report->targetLabel(), 'report' => $report->id];
    }

    /** Seuil atteint exactement par ce signalement : une seule alerte par objet (F-MOD-11). */
    private function alertIfMultiple(Report $report): void
    {
        $open = Report::query()->where('target_type', $report->target_type)->where('target_id', $report->target_id)
            ->whereIn('status', ReportStatus::open())->count();

        if ($open !== (int) config('koryhub.moderation.multiple_threshold', 3)) {
            return;
        }

        User::query()->where('role', Role::Admin)->where('status', AccountStatus::Active)->each(
            fn (User $admin) => $admin->notify(new ModuleNotification(NotificationType::AdminReportsMultiple, $this->params($report) + ['count' => $open]))
        );
    }

    private function ownerId(Module|ModuleVersion|User|Review $target): string
    {
        return match (true) {
            $target instanceof Module => $target->author_id,
            $target instanceof ModuleVersion => $target->module->author_id,
            $target instanceof User => $target->id,
            $target instanceof Review => $target->user_id,
        };
    }
}
