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
