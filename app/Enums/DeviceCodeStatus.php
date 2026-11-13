<?php

namespace App\Enums;

/**
 * Statut d'un code de connexion du CLI (BACKEND-SCHEMA §6, §7) : en attente → autorisé ou
 * refusé par le développeur, ou expiré ; autorisé → utilisé à la remise du jeton.
 */
enum DeviceCodeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Expired = 'expired';
    case Consumed = 'consumed';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Denied, self::Expired],
            self::Approved => [self::Consumed],
            default => [],
        };
    }

    public function canBecome(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
