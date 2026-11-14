<?php

namespace App\Services\Accounts;

use App\Enums\AccountStatus;
use App\Enums\NotificationType;
use App\Enums\Visibility;
use App\Exceptions\InvalidTransition;
use App\Models\Module;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Community\ReviewService;
use App\Services\Modules\ModuleLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cycle de vie d'un compte (BACKEND-SCHEMA §6) : Actif → Suspendu → Supprimé.
 * W01 livre la suppression ; W07 la suspension et la réactivation par un admin.
 * Chaque module ajoute ici ce qu'il faut effacer à la suppression (jetons, modules privés…).
 */
class AccountService
{
    public function __construct(private AuditLogger $audit, private ModuleLifecycle $modules) {}

    /**
     * Supprime un compte (F-AUT-17, RGPD) : données personnelles effacées, sessions fermées,
     * jetons du CLI révoqués, journal de sécurité et passkeys effacés, modules privés supprimés
     * (les modules publics restent) ; la ligne reste, son nom @auteur est réservé.
     */
    public function delete(User $user, ?User $actor = null, ?string $reason = null): void
    {
        if ($user->status === AccountStatus::Deleted) {
            throw new InvalidTransition($user->status, AccountStatus::Deleted);
        }

        $user->modules()->where('visibility', Visibility::Private)->get()
            ->each(fn (Module $module) => $this->modules->deleteModule($module, $actor ?? $user));

        $previousStatus = $user->status;
        $avatar = $user->avatar_path;

        DB::transaction(function () use ($user, $actor, $reason, $previousStatus) {
            // Jetons du CLI révoqués sans être supprimés (historique, TRD §7.2).
            $user->tokens()->usable()->update(['revoked_at' => now(), 'revoked_by' => ($actor ?? $user)->getKey()]);
            $user->securityEvents()->delete();
            $user->moduleDownloads()->delete();
            $user->stars()->delete();
            $user->watches()->delete();
            // Ses avis restent (le nom @auteur est gardé) mais ne sont plus vérifiés : notes recalculées.
            $user->reviews()->with('module')->get()->each(fn ($review) => app(ReviewService::class)->refreshRating($review->module));
            $user->passkeys()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->forceFill([
                'name' => null,
                'email' => null,
                'email_verified_at' => null,
                'password' => null,
                'remember_token' => null,
                'country' => null,
                'avatar_path' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'status' => AccountStatus::Deleted,
                'erased_at' => now(),
            ])->save();

            $this->audit->record(
                'account.deleted',
                $user,
                ['status' => [$previousStatus, AccountStatus::Deleted]],
                $reason,
                $actor ?? $user,
            );
        });

        if ($avatar !== null) {
            Storage::disk(config('koryhub.avatars.disk'))->delete($avatar);
        }
    }

    /**
     * Suspend un compte (F-AUT-15, F-ADM-02, W07) : sessions fermées ; jetons refusés (403
     * account_suspended) mais conservés ; modules publics toujours téléchargeables ; e-mail au
     * développeur avec le motif.
     */
    public function suspend(User $user, User $admin, string $reason): void
    {
        if ($user->status !== AccountStatus::Active) {
            throw new InvalidTransition($user->status, AccountStatus::Suspended);
        }

        DB::transaction(function () use ($user, $admin, $reason) {
            $user->forceFill(['status' => AccountStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => $reason])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->audit->record('account.suspended', $user, ['status' => [AccountStatus::Active, AccountStatus::Suspended]], $reason, $admin);
        });

        $user->notify(new ModuleNotification(NotificationType::AccountSuspended, ['reason' => $reason]));
    }

    /** Réactive un compte suspendu : il retrouve l'accès au site et à ses jetons encore valides. */
    public function reactivate(User $user, User $admin, string $reason): void
    {
        if ($user->status !== AccountStatus::Suspended) {
            throw new InvalidTransition($user->status, AccountStatus::Active);
        }

        DB::transaction(function () use ($user, $admin, $reason) {
            $user->forceFill(['status' => AccountStatus::Active, 'suspended_at' => null, 'suspension_reason' => null])->save();
            $this->audit->record('account.reactivated', $user, ['status' => [AccountStatus::Suspended, AccountStatus::Active]], $reason, $admin);
        });
    }
}
