<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Champ modifié par un événement d'audit : valeur avant et après (TRD §21).
 *
 * @property string $id
 * @property string $audit_event_id
 * @property string $field
 * @property string|null $before
 * @property string|null $after
 */
#[Fillable(['audit_event_id', 'field', 'before', 'after'])]
class AuditEventChange extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit events are append-only.'));
    }

    /** @return BelongsTo<AuditEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(AuditEvent::class, 'audit_event_id');
    }
}
