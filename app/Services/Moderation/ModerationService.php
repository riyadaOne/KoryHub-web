<?php

namespace App\Services\Moderation;

use App\Enums\AccountStatus;
use App\Enums\NotificationType;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Exceptions\InvalidTransition;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\PersonalAccessToken;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Accounts\AccountService;
use App\Services\Audit\AuditLogger;
use App\Services\Cli\CliTokenService;
use App\Services\Community\ModuleAudience;
use App\Services\Community\ReviewService;
use App\Services\Modules\ModuleLifecycle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Modération par l'admin (W07.2, F-MOD-10 à F-MOD-15, F-RCH-13) : prendre un signalement, le
 * clore sans suite ou avec une action ; avertissement de sécurité sur une version (posé, levé),
 * retrait légal, avis masqué ou réaffiché. Chaque décision exige un motif, est consignée dans
 * l'audit et clôt tous les signalements ouverts sur le même objet (choix 1 des maquettes W07) ;
 * chaque auteur de signalement reçoit un résultat générique (décision 4a).
 */
class ModerationService
{
    public function __construct(private AuditLogger $audit, private ReportService $reports) {}

    /** Prend le signalement : en examen, attribué à l'admin. */
    public function take(Report $report, User $admin): void
    {
        $this->requireAdmin($admin);
        $this->transition($report, ReportStatus::InReview, $admin, null, ['assigned_to' => $admin->id, 'examined_at' => now()]);
    }

    /** Clôt sans suite tous les signalements ouverts sur l'objet. */
    public function closeNoAction(Report $report, User $admin, string $note): void
    {
        $this->requireAdmin($admin);
        $this->closeTarget($report, $admin, null, $this->requireText($note));
    }

    /**
     * Avertissement de sécurité sur une version publiée ou dépréciée (F-MOD-10, F-MOD-14) :
     * bandeau sur la fiche, état « at_risk » dans kory audit ; auteur, abonnés et utilisateurs prévenus.
     */
    public function markAtRisk(ModuleVersion $version, User $admin, string $message, ?Report $report = null): void
    {
        $this->requireAdmin($admin);
        $message = $this->requireText($message);
        if (! $version->status->isAvailable() || $version->at_risk_at !== null) {
            throw new ModerationRefused('moderation.errors.not_allowed');
        }

        DB::transaction(function () use ($version, $admin, $message, $report) {
            $version->forceFill(['at_risk_at' => now(), 'at_risk_message' => $message, 'at_risk_report_id' => $report?->id])->save();
            $this->audit->record('version.at_risk', $version, ['at_risk_at' => [null, $version->at_risk_at]], $message, $admin);
            if ($report !== null) {
                $this->closeTarget($report, $admin, ReportResolution::SecurityWarning, $message);
            }
        });

        $this->notifyVersion($version, NotificationType::VersionAtRisk, ['reason' => $message]);
    }

    /** Lève l'avertissement (décision 3a) : motif et audit, sans nouvelle notification. */
    public function clearAtRisk(ModuleVersion $version, User $admin, string $reason): void
    {
        $this->requireAdmin($admin);
        $reason = $this->requireText($reason);
        if ($version->at_risk_at === null) {
            throw new ModerationRefused('moderation.errors.not_allowed');
        }

        DB::transaction(function () use ($version, $admin, $reason) {
            $before = $version->at_risk_at;
            $version->forceFill(['at_risk_at' => null, 'at_risk_message' => null, 'at_risk_report_id' => null])->save();
            $this->audit->record('version.at_risk_cleared', $version, ['at_risk_at' => [$before, null]], $reason, $admin);
        });
    }

    /**
     * Retrait légal (F-MOD-13) : statut « retirée » définitif, archive, README et contenus en cache
     * supprimés ; restent l'empreinte, la liste des chemins et la trace (qui, quand, pourquoi).
     * Le message public est court ; le motif complet ne va que dans l'audit (choix 3 des maquettes).
     */
    public function removeVersion(ModuleVersion $version, User $admin, string $publicMessage, string $reason, ?Report $report = null): void
    {
        $this->requireAdmin($admin);
        $publicMessage = mb_substr($this->requireText($publicMessage), 0, 255);
        $reason = $this->requireText($reason);
        if (! in_array(VersionStatus::Removed, $version->status->allowedNext(), true)) {
            throw new InvalidTransition($version->status, VersionStatus::Removed);
        }

        $archive = $version->archive_path;
        $files = $version->files()->pluck('path_hash');

        DB::transaction(function () use ($version, $admin, $publicMessage, $reason, $report) {
            $previous = $version->status;
            $version->forceFill([
                'status' => VersionStatus::Removed,
                'removed_at' => now(),
                'removed_by' => $admin->id,
                'removed_reason' => $publicMessage,
                'archive_path' => null,
                'readme' => null,
            ])->save();
            $this->audit->record('version.removed', $version, ['status' => [$previous, VersionStatus::Removed], 'archive_sha256' => [$version->archive_sha256, $version->archive_sha256]], $reason, $admin);
            app(ModuleLifecycle::class)->refreshLatestVersion($version->module);
            if ($report !== null) {
                $this->closeTarget($report, $admin, ReportResolution::LegalRemoval, $reason);
            }
        });

        if ($archive !== null) {
            Storage::disk(config('koryhub.publication.archives_disk'))->delete($archive);
        }
        $files->each(fn (string $hash) => Cache::forget("version-file:{$version->id}:{$hash}"));

        $this->notifyVersion($version, NotificationType::VersionRemoved, ['reason' => $publicMessage]);
    }

    /** Masque un avis (F-RCH-13) : ni affiché ni compté ; son auteur le voit encore. */
    public function hideReview(Review $review, User $admin, string $reason, ?Report $report = null): void
    {
        $this->requireAdmin($admin);
        $reason = $this->requireText($reason);
        if ($review->hidden_at !== null) {
            throw new ModerationRefused('moderation.errors.not_allowed');
        }

        DB::transaction(function () use ($review, $admin, $reason, $report) {
            $review->forceFill(['hidden_at' => now(), 'hidden_by' => $admin->id, 'hidden_report_id' => $report?->id])->save();
            $this->audit->record('review.hidden', $review, ['hidden_at' => [null, $review->hidden_at]], $reason, $admin);
            app(ReviewService::class)->refreshRating($review->module);
            if ($report !== null) {
                $this->closeTarget($report, $admin, ReportResolution::ReviewHidden, $reason);
            }
        });
    }

    /** Réaffiche un avis masqué. */
    public function showReview(Review $review, User $admin, string $reason): void
    {
        $this->requireAdmin($admin);
        $reason = $this->requireText($reason);
        if ($review->hidden_at === null) {
            throw new ModerationRefused('moderation.errors.not_allowed');
        }

        DB::transaction(function () use ($review, $admin, $reason) {
            $before = $review->hidden_at;
            $review->forceFill(['hidden_at' => null, 'hidden_by' => null, 'hidden_report_id' => null])->save();
            $this->audit->record('review.shown', $review, ['hidden_at' => [$before, null]], $reason, $admin);
            app(ReviewService::class)->refreshRating($review->module);
        });
    }

    /**
     * Suspend un compte (F-AUT-15, F-ADM-02) ; avec un signalement, le clôt avec l'action. Jamais
     * son propre compte ni un autre admin (rôles gérés en W08).
     */
    public function suspendAccount(User $user, User $admin, string $reason, ?Report $report = null): void
    {
        $this->requireAdmin($admin);
        $this->requireTargetAccount($user, $admin);
        $reason = $this->requireText($reason);

        DB::transaction(function () use ($user, $admin, $reason, $report) {
            app(AccountService::class)->suspend($user, $admin, $reason);
            if ($report !== null) {
                $this->closeTarget($report, $admin, ReportResolution::AccountSuspended, $reason);
            }
        });
    }

    public function reactivateAccount(User $user, User $admin, string $reason): void
    {
        $this->requireAdmin($admin);
        $this->requireTargetAccount($user, $admin);
        app(AccountService::class)->reactivate($user, $admin, $this->requireText($reason));
    }

    /** Supprime un compte (F-ADM-02) par le même effacement que la suppression par le développeur. */
    public function deleteAccount(User $user, User $admin, string $reason, ?Report $report = null): void
    {
        $this->requireAdmin($admin);
        $this->requireTargetAccount($user, $admin);
        $reason = $this->requireText($reason);

        if ($report !== null) {
            $this->closeTarget($report, $admin, ReportResolution::AccountDeleted, $reason);
        }
        app(AccountService::class)->delete($user, $admin, $reason);
    }

    /** Révocation d'urgence d'un jeton du CLI (F-AUT-15) : motif et audit ; la valeur n'est jamais vue. */
    public function revokeToken(PersonalAccessToken $token, User $admin, string $reason): void
    {
        $this->requireAdmin($admin);
        app(CliTokenService::class)->revoke($token, $admin, $this->requireText($reason));
    }

    /** Mise en avant sur l'accueil (F-RCH-12) : décision humaine, module public seulement, journalisée. */
    public function setFeatured(Module $module, User $admin, bool $featured): void
    {
        $this->requireAdmin($admin);
        if ($module->visibility !== Visibility::Public || $module->is_featured === $featured) {
            throw new ModerationRefused('moderation.errors.not_allowed');
        }

        DB::transaction(function () use ($module, $admin, $featured) {
            $module->forceFill(['is_featured' => $featured, 'featured_at' => $featured ? now() : null])->save();
            $this->audit->record('module.featured', $module, ['is_featured' => [! $featured, $featured]], actor: $admin);
        });
    }

    /**
     * Clôt tous les signalements ouverts sur l'objet de $report (un signalement encore « ouvert »
     * passe par « en examen ») ; chaque auteur reçoit une seule notification.
     */
    public function closeTarget(Report $report, User $admin, ?ReportResolution $resolution, string $note): void
    {
        $next = $resolution === null ? ReportStatus::ClosedNoAction : ReportStatus::ClosedWithAction;
        $open = Report::query()->where('target_type', $report->target_type)->where('target_id', $report->target_id)
            ->whereIn('status', ReportStatus::open())->get();
        if ($open->isEmpty()) {
            throw new InvalidTransition($report->status, $next);
        }

        DB::transaction(function () use ($open, $admin, $resolution, $note, $next) {
            foreach ($open as $item) {
                if ($item->status === ReportStatus::Open) {
                    $this->transition($item, ReportStatus::InReview, $admin, null, ['assigned_to' => $admin->id, 'examined_at' => now()]);
                }
                $this->transition($item, $next, $admin, $note, [
                    'resolution' => $resolution,
                    'resolution_note' => $note,
                    'resolved_at' => now(),
                    'resolved_by' => $admin->id,
                ]);
            }
        });

        $open->unique('reporter_id')->each(fn (Report $item) => $item->reporter->notify(new ModuleNotification(NotificationType::ReportClosed, $this->reports->params($item))));
    }

    /** Relance les admins, une fois par jour, pour chaque signalement ouvert depuis plus de 48 h (F-MOD-12). */
    public function remindStale(): int
    {
        $admins = User::query()->where('role', Role::Admin)->where('status', AccountStatus::Active)->get();
        $count = 0;

        Report::query()
            ->whereIn('status', ReportStatus::open())
            ->where('created_at', '<=', now()->subHours((int) config('koryhub.moderation.stale_hours', 48)))
            ->where(fn ($q) => $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<=', now()->subDay()))
            ->each(function (Report $report) use ($admins, &$count) {
                $admins->each(fn (User $admin) => $admin->notify(new ModuleNotification(NotificationType::AdminReportStale, $this->reports->params($report))));
                $report->forceFill(['last_reminder_at' => now()])->saveQuietly();
                $count++;
            });

        return $count;
    }

    /** @param  array<string, mixed>  $attributes */
    private function transition(Report $report, ReportStatus $next, User $admin, ?string $reason, array $attributes): void
    {
        if (! in_array($next, $report->status->allowedNext(), true)) {
            throw new InvalidTransition($report->status, $next);
        }

        $previous = $report->status;
        $report->forceFill(['status' => $next, ...$attributes])->save();
        $this->audit->record('report.status_changed', $report, ['status' => [$previous, $next]], $reason, $admin);
    }

    /** Auteur de la version, puis abonnés et utilisateurs de la version (F-MOD-14). */
    private function notifyVersion(ModuleVersion $version, NotificationType $type, array $params): void
    {
        $params = ['module' => $version->module->fullName(), 'version' => $version->version, ...$params];
        $version->module->author->notify(new ModuleNotification($type, $params));
        app(ModuleAudience::class)->versionModerated($version, $type, $params);
    }

    private function requireAdmin(User $admin): void
    {
        if (! $admin->isAdmin()) {
            throw new ModerationRefused('moderation.errors.not_allowed');
        }
    }

    private function requireTargetAccount(User $user, User $admin): void
    {
        if ($user->is($admin) || $user->isAdmin()) {
            throw new ModerationRefused('moderation.errors.not_allowed');
        }
    }

    private function requireText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new ModerationRefused('moderation.errors.reason_required');
        }

        return mb_substr($text, 0, 5000);
    }
}
