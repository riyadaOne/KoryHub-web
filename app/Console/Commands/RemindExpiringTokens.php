<?php

namespace App\Console\Commands;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\SecurityNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Relance des jetons qui expirent dans 7 jours (F-AUT-12) : une seule fois par jeton,
 * par e-mail et dans la cloche. Planifiée chaque jour (routes/console.php).
 */
#[Signature('koryhub:tokens-remind')]
#[Description('Prévient les développeurs dont un jeton du CLI expire dans 7 jours')]
class RemindExpiringTokens extends Command
{
    public function handle(): int
    {
        $count = 0;

        PersonalAccessToken::query()
            ->usable()
            ->whereNull('expiry_reminded_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(PersonalAccessToken::EXPIRING_SOON_DAYS))
            ->with('tokenable')
            ->each(function (PersonalAccessToken $token) use (&$count) {
                $owner = $token->tokenable;

                if ($owner instanceof User && $owner->isActive() && $owner->email !== null) {
                    $owner->notify(new SecurityNotification('token_expiring', [
                        'token' => $token->name,
                        'expires_at' => $token->expires_at->toIso8601String(),
                    ]));
                    $count++;
                }

                $token->forceFill(['expiry_reminded_at' => now()])->save();
            });

        $this->info("{$count} relance(s) envoyée(s).");

        return self::SUCCESS;
    }
}
