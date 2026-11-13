<?php

namespace App\Listeners;

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Services\Accounts\SecurityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

/**
 * Remplit le journal de sécurité à partir des événements de connexion, de Fortify et des
 * passkeys (F-AUT-19). Le changement de mot de passe et d'e-mail depuis les réglages est
 * noté par les écrans eux-mêmes.
 */
class RecordSecurityEvents
{
    public function __construct(private SecurityLog $log) {}

    public function handleLogin(Login $event): void
    {
        $this->recordFor($event->user, SecurityEventType::Login);
    }

    public function handleFailed(Failed $event): void
    {
        $this->recordFor($event->user, SecurityEventType::LoginFailed);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->recordFor($event->user, SecurityEventType::PasswordChanged);
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->recordFor($event->user, SecurityEventType::TwoFactorEnabled);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->recordFor($event->user, SecurityEventType::TwoFactorDisabled);
    }

    public function handlePasskeyRegistered(PasskeyRegistered $event): void
    {
        $this->recordFor($event->user, SecurityEventType::PasskeyAdded);
    }

    public function handlePasskeyDeleted(PasskeyDeleted $event): void
    {
        $this->recordFor($event->user, SecurityEventType::PasskeyRemoved);
    }

    /** Une tentative sur un e-mail inconnu n'a pas de compte : rien n'est noté. */
    private function recordFor(mixed $user, SecurityEventType $type): void
    {
        if ($user instanceof User) {
            $this->log->record($user, $type);
        }
    }
}
