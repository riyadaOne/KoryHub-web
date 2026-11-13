<?php

namespace App\Models;

use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Événement du journal d'audit (BACKEND-SCHEMA §4.6) : écrit une fois, jamais modifié.
 * Écrire par App\Services\Audit\AuditLogger.
 *
 * @property string $id
 * @property Carbon $occurred_at
 * @property ActorType $actor_type
 * @property string|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $reason
 */
#[Fillable(['occurred_at', 'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'reason'])]
class AuditEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit events are append-only.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'actor_type' => ActorType::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return HasMany<AuditEventChange, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(AuditEventChange::class);
    }
}
