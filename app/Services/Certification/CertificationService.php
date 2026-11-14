<?php

namespace App\Services\Certification;

use App\Enums\CertificationStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\Visibility;
use App\Exceptions\InvalidTransition;
use App\Models\Certification;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Search\ScoreCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Certification, partie développeur (W06.1, F-MOD-01, F-MOD-02, F-MOD-07, F-MOD-08, F-MOD-17) :
 * demande après contrôles préliminaires, réponse à un complément (texte ou nouvelle version du
 * même module, contrôles relancés), relance quotidienne. Les décisions de l'admin sont en W06.2.
 */
class CertificationService
{
    /** Jours sans réponse avant la première relance, puis une relance par jour (F-MOD-07). */
    public const REMINDER_AFTER_DAYS = 7;

    public function __construct(private PreliminaryChecks $checks, private AuditLogger $audit) {}

    /** Le compte peut demander la certification de ce module : auteur, e-mail vérifié, module public. */
    public function canRequest(Module $module, ?User $user): bool
    {
        return $user !== null
            && $user->id === $module->author_id
            && $user->hasVerifiedEmail()
            && $module->visibility === Visibility::Public;
    }

    /** Ouvre un dossier si tous les contrôles passent ; sinon CertificationRefused avec les résultats. */
    public function request(ModuleVersion $version, User $user): Certification
    {
        $module = $version->module;
        if (! $this->canRequest($module, $user)) {
            throw new CertificationRefused('certification.errors.not_allowed');
        }

        $results = $this->checks->run($version);
        if (! PreliminaryChecks::allPassed($results)) {
            throw new CertificationRefused('certification.errors.checks_failed', $results);
        }

        $certification = DB::transaction(function () use ($version, $module, $user, $results) {
            $certification = Certification::create([
                'module_id' => $module->id,
                'version_id' => $version->id,
                'requested_by' => $user->id,
                'status' => CertificationStatus::Requested,
                'requested_at' => now(),
            ]);
            $this->storeChecks($certification, $version, $results);
            $this->audit->record('certification.requested', $certification, ['status' => [null, CertificationStatus::Requested], 'version' => [null, $version->version]], actor: $user);

            return $certification;
        });

        $this->notifyAdmins($certification);

        return $certification;
    }

    /**
     * Réponse à un complément : un texte, et éventuellement une nouvelle version publiée du même
     * module (contrôles relancés ; le dossier passe sur elle). Le dossier revient en audit.
     */
    public function respond(Certification $certification, User $user, string $body, ?ModuleVersion $newVersion = null): void
    {
        if ($user->id !== $certification->requested_by) {
            throw new CertificationRefused('certification.errors.not_allowed');
        }
        if ($certification->status !== CertificationStatus::ComplementRequired) {
            throw new InvalidTransition($certification->status, CertificationStatus::InAudit);
        }
        if ($newVersion !== null) {
            if ($newVersion->module_id !== $certification->module_id || $newVersion->id === $certification->version_id) {
                throw new CertificationRefused('certification.errors.version_not_allowed');
            }
            $results = $this->checks->run($newVersion, $certification);
            if (! PreliminaryChecks::allPassed($results)) {
                throw new CertificationRefused('certification.errors.checks_failed', $results);
            }
        }

        DB::transaction(function () use ($certification, $user, $body, $newVersion) {
            $certification->messages()->create(['author_id' => $user->id, 'body' => mb_substr(trim($body), 0, 5000), 'version_id' => $newVersion?->id]);

            $changes = ['status' => [CertificationStatus::ComplementRequired, CertificationStatus::InAudit]];
            if ($newVersion !== null) {
                $changes['version'] = [$certification->version->version, $newVersion->version];
                $this->storeChecks($certification, $newVersion, $this->checks->run($newVersion, $certification));
                $certification->version_id = $newVersion->id;
            }
            $certification->forceFill(['status' => CertificationStatus::InAudit, 'last_reminder_at' => null])->save();
            $this->audit->record('certification.responded', $certification, $changes, actor: $user);
        });

        $this->notifyAdmins($certification->fresh());
    }

    /** Admin : prend le dossier (en audit, attribué). */
    public function take(Certification $certification, User $admin): void
    {
        $this->decide($certification, $admin, CertificationStatus::InAudit, [
            'assigned_to' => $admin->id,
            'audit_started_at' => $certification->audit_started_at ?? now(),
        ]);
    }

    /** Admin : demande un complément ; le motif devient un message du dossier, l'auteur est prévenu. */
    public function requestComplement(Certification $certification, User $admin, string $reason): void
    {
        $reason = $this->requireText($reason);
        $this->decide($certification, $admin, CertificationStatus::ComplementRequired, [
            'complement_requested_at' => now(),
            'last_reminder_at' => null,
        ], $reason, function (Certification $c) use ($admin, $reason) {
            $c->messages()->create(['author_id' => $admin->id, 'body' => $reason]);
        });

        $certification->requester->notify(new ModuleNotification(NotificationType::CertificationComplement, $this->params($certification) + ['reason' => $reason]));
    }

    /** Admin : accorde le badge à la version du dossier, avec les commentaires repris dans le rapport. */
    public function grant(Certification $certification, User $admin, string $architectureComment, string $documentationComment): void
    {
        $this->decide($certification, $admin, CertificationStatus::Granted, [
            'decided_at' => now(),
            'decided_by' => $admin->id,
            'architecture_comment' => $this->requireText($architectureComment),
            'documentation_comment' => $this->requireText($documentationComment),
        ]);

        $this->refreshScore($certification);
        $certification->requester->notify(new ModuleNotification(NotificationType::CertificationGranted, $this->params($certification)));
    }

    /** Admin : refuse, avec un motif montré à l'auteur. */
    public function refuse(Certification $certification, User $admin, string $reason): void
    {
        $reason = $this->requireText($reason);
        $this->decide($certification, $admin, CertificationStatus::Refused, [
            'decided_at' => now(),
            'decided_by' => $admin->id,
            'decision_reason' => $reason,
        ], $reason);

        $certification->requester->notify(new ModuleNotification(NotificationType::CertificationRefused, $this->params($certification) + ['reason' => $reason]));
    }

    /** Admin : révoque un badge accordé ; le rapport reste tel quel, la page fait foi. */
    public function revoke(Certification $certification, User $admin, string $reason): void
    {
        $reason = $this->requireText($reason);
        $this->decide($certification, $admin, CertificationStatus::Revoked, [
            'revoked_at' => now(),
            'revoked_by' => $admin->id,
            'revoked_reason' => $reason,
        ], $reason);

        $this->refreshScore($certification);
        $certification->requester->notify(new ModuleNotification(NotificationType::CertificationRevoked, $this->params($certification) + ['reason' => $reason]));
    }

    /**
     * Transition par un admin : vérifiée, consignée dans l'audit avec son motif (F-MOD-15).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function decide(Certification $certification, User $admin, CertificationStatus $next, array $attributes, ?string $reason = null, ?callable $also = null): void
    {
        if (! $admin->isAdmin()) {
            throw new CertificationRefused('certification.errors.not_allowed');
        }
        if (! in_array($next, $certification->status->allowedNext(), true)) {
            throw new InvalidTransition($certification->status, $next);
        }

        DB::transaction(function () use ($certification, $admin, $next, $attributes, $reason, $also) {
            $previous = $certification->status;
            $certification->forceFill(['status' => $next, ...$attributes])->save();
            if ($also !== null) {
                $also($certification);
            }
            $this->audit->record('certification.status_changed', $certification, ['status' => [$previous, $next]], $reason, $admin);
        });
    }

    /** Le badge compte dans le score (TRD §14) : recalcul immédiat, sans attendre la tâche horaire. */
    private function refreshScore(Certification $certification): void
    {
        $module = $certification->module->fresh(['latestVersion.grantedCertification']);
        $module->forceFill(['score' => app(ScoreCalculator::class)->score($module), 'score_computed_at' => now()])->saveQuietly();
    }

    private function requireText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new CertificationRefused('certification.errors.reason_required');
        }

        return mb_substr($text, 0, 5000);
    }

    /** Relance chaque jour les dossiers en complément depuis 7 jours sans réponse ; renvoie le nombre. */
    public function remindStale(): int
    {
        $count = 0;
        Certification::query()
            ->where('status', CertificationStatus::ComplementRequired)
            ->where('complement_requested_at', '<=', now()->subDays(self::REMINDER_AFTER_DAYS))
            ->where(fn ($q) => $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<=', now()->subDay()))
            ->with(['requester', 'module.author', 'version'])
            ->each(function (Certification $certification) use (&$count) {
                $certification->requester->notify(new ModuleNotification(NotificationType::CertificationComplement, $this->params($certification) + ['reason' => __('certification.reminder')]));
                $certification->forceFill(['last_reminder_at' => now()])->saveQuietly();
                $count++;
            });

        return $count;
    }

    /** Paramètres des notifications : module, version, dossier. */
    public function params(Certification $certification): array
    {
        return [
            'module' => $certification->module->fullName(),
            'version' => $certification->version->version,
            'certification' => $certification->id,
        ];
    }

    /** @param  list<array{code: string, passed: bool, detail: ?string}>  $results */
    private function storeChecks(Certification $certification, ModuleVersion $version, array $results): void
    {
        foreach ($results as $result) {
            $certification->checks()->create([...$result, 'version_id' => $version->id, 'checked_at' => now()]);
        }
    }

    /** Tous les admins : dossier à traiter (nouvelle demande ou réponse à un complément). */
    private function notifyAdmins(Certification $certification): void
    {
        User::query()->where('role', Role::Admin)->each(
            fn (User $admin) => $admin->notify(new ModuleNotification(NotificationType::AdminCertificationRequested, $this->params($certification)))
        );
    }
}
