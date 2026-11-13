<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Donne le rôle admin à un compte existant (W01). Seul moyen de devenir admin :
 * aucun écran ne le permet. Lancée par le développeur (elle modifie la base).
 */
#[Signature('koryhub:admin {email : Adresse e-mail du compte} {--revoke : Retirer le rôle admin}')]
#[Description('Donne (ou retire) le rôle admin à un compte KoryHub')]
class MakeAdmin extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null || $user->status !== AccountStatus::Active) {
            $this->error('Aucun compte actif avec cette adresse e-mail.');

            return self::FAILURE;
        }

        $role = $this->option('revoke') ? Role::Developer : Role::Admin;

        if ($user->role === $role) {
            $this->info("@{$user->handle} a déjà le rôle {$role->value}.");

            return self::SUCCESS;
        }

        $previous = $user->role;
        $user->forceFill(['role' => $role])->save();
        $audit->record('account.role_changed', $user, ['role' => [$previous, $role]], 'php artisan koryhub:admin', bySystem: true);

        $this->info("@{$user->handle} a maintenant le rôle {$role->value}.");

        return self::SUCCESS;
    }
}
