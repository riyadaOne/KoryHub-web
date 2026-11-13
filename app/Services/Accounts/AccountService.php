<?php

namespace App\Services\Accounts;

use App\Enums\AccountStatus;
use App\Exceptions\InvalidTransition;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cycle de vie d'un compte (BACKEND-SCHEMA §6) : Actif → Suspendu → Supprimé.
 * W01 livre la suppression ; la suspension et la réactivation arrivent en W07.
 * Chaque module ajoute ici ce qu'il faut effacer à la suppression (jetons, modules privés…).
 */
class AccountService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Supprime un compte (F-AUT-17, RGPD) : données personnelles effacées, sessions fermées,
     * journal de sécurité et passkeys effacés ; la ligne reste, son nom @auteur est réservé.
     */
    public function delete(User $user, ?User $actor = null, ?string $reason = null): void
    {
        if ($user->status === AccountStatus::Deleted) {
            throw new InvalidTransition($user->status, AccountStatus::Deleted);
        }

        $previousStatus = $user->status;
        $avatar = $user->avatar_path;

        DB::transaction(function () use ($user, $actor, $reason, $previousStatus) {
            $user->securityEvents()->delete();
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
}
