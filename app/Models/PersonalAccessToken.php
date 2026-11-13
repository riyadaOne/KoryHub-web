<?php

namespace App\Models;

use App\Enums\TokenOrigin;
use App\Enums\TokenStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Jeton du CLI (TRD §7.2, BACKEND-SCHEMA §4.1) : haché (SHA-256), préfixé kory_, jamais
 * supprimé ; révoqué par revoked_at, refusé dès qu'il est révoqué ou expiré (F-AUT-08, F-AUT-09).
 *
 * @property string $id
 * @property string $tokenable_type
 * @property string $tokenable_id
 * @property string $name
 * @property string $token
 * @property TokenOrigin $origin
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_by
 * @property Carbon|null $expiry_reminded_at
 * @property Carbon $created_at
 */
#[Fillable(['name', 'token', 'abilities', 'origin', 'expires_at', 'last_used_at', 'revoked_at', 'revoked_by', 'expiry_reminded_at'])]
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    /** Un jeton expire « bientôt » dans les 7 derniers jours (relance F-AUT-12, badge S-41). */
    public const EXPIRING_SOON_DAYS = 7;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'origin' => TokenOrigin::class,
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expiry_reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function status(): TokenStatus
    {
        return match (true) {
            $this->revoked_at !== null => TokenStatus::Revoked,
            $this->expires_at !== null && $this->expires_at->isPast() => TokenStatus::Expired,
            $this->expires_at !== null && $this->expires_at->lte(now()->addDays(self::EXPIRING_SOON_DAYS)) => TokenStatus::ExpiringSoon,
            default => TokenStatus::Active,
        };
    }

    /** Jetons encore utilisables : ni révoqués ni expirés. */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
