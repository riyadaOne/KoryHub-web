<?php

namespace App\Services\Accounts;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Écrit dans le journal de sécurité d'un compte (F-AUT-19) : type, adresse IP et
 * navigateur de la requête en cours. Ces données personnelles restent hors du journal
 * d'audit et sont effacées à la suppression du compte (TRD §21).
 */
class SecurityLog
{
    public function record(User $user, SecurityEventType $type): SecurityEvent
    {
        $request = request();

        return $user->securityEvents()->create([
            'type' => $type,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'occurred_at' => now(),
        ]);
    }
}
