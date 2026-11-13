<?php

namespace App\Models;

use App\Enums\SecurityEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Événement du journal de sécurité d'un compte (F-AUT-19) : écrit par
 * App\Services\Accounts\SecurityLog, visible par l'intéressé seul.
 *
 * @property string $id
 * @property string $user_id
 * @property SecurityEventType $type
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $occurred_at
 */
#[Fillable(['user_id', 'type', 'ip', 'user_agent', 'occurred_at'])]
class SecurityEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SecurityEventType::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
