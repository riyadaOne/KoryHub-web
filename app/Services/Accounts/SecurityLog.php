<?php

namespace App\Services\Accounts;

use App\Enums\SecurityEventType;
use App\Models\PersonalAccessToken;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\SecurityNotification;
use Illuminate\Support\Str;

/**
 * Écrit dans le journal de sécurité d'un compte (F-AUT-19) : type, adresse IP et
 * navigateur de la requête en cours, jeton concerné. Ces données personnelles restent hors
 * du journal d'audit et sont effacées à la suppression du compte (TRD §21).
 *
 * Certains événements préviennent aussi le développeur par e-mail et dans la cloche ;
 * ces notifications de sécurité ne sont pas désactivables (TRD §19, T-32).
 */
class SecurityLog
{
    /** Événements notifiés (F-AUT-11, F-AUT-12, F-AUT-21). */
    public const NOTIFIED = [
        SecurityEventType::CliLogin,
        SecurityEventType::TokenCreated,
        SecurityEventType::TokenRevoked,
        SecurityEventType::PasswordChanged,
    ];

    public function record(User $user, SecurityEventType $type, ?PersonalAccessToken $token = null): SecurityEvent
    {
        $request = request();

        $event = $user->securityEvents()->create([
            'type' => $type,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'token_id' => $token?->getKey(),
            'occurred_at' => now(),
        ]);

        if (in_array($type, self::NOTIFIED, true) && $user->email !== null) {
            $user->notify(new SecurityNotification($type->value, [
                'token' => $token?->name,
                'ip' => $event->ip,
                'expires_at' => $token?->expires_at?->toIso8601String(),
            ]));
        }

        return $event;
    }
}
