<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Signalement (F-MOD-09 à F-MOD-12, BACKEND-SCHEMA §4.5) : un module, une version, un compte ou
 * un avis. reporter_id n'est jamais montré à la personne visée ; seuls les admins le voient.
 *
 * @property string $id
 * @property string $reporter_id
 * @property ReportTargetType $target_type
 * @property string $target_id
 * @property ReportReason $reason
 * @property string|null $details
 * @property ReportStatus $status
 * @property string|null $assigned_to
 * @property Carbon|null $examined_at
 * @property ReportResolution|null $resolution
 * @property string|null $resolution_note
 * @property Carbon|null $resolved_at
 * @property string|null $resolved_by
 * @property Carbon|null $last_reminder_at
 * @property Carbon $created_at
 * @property-read User $reporter
 * @property-read User|null $assignee
 */
#[Fillable(['reporter_id', 'target_type', 'target_id', 'reason', 'details', 'status'])]
class Report extends Model
{
    use HasUuids, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'target_type' => ReportTargetType::class,
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolution' => ReportResolution::class,
            'examined_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_reminder_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Objet visé, même supprimé logiquement ; null s'il n'existe plus. */
    public function target(): Module|ModuleVersion|User|Review|null
    {
        $class = match ($this->target_type) {
            ReportTargetType::Module => Module::class,
            ReportTargetType::Version => ModuleVersion::class,
            ReportTargetType::User => User::class,
            ReportTargetType::Review => Review::class,
        };
        $query = $class::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->withTrashed();
        }

        return $query->find($this->target_id);
    }

    /** Module concerné par la cible (module, version, avis), ou null pour un compte. */
    public function targetModule(): ?Module
    {
        $target = $this->target();

        return match (true) {
            $target instanceof Module => $target,
            $target instanceof ModuleVersion, $target instanceof Review => $target->module()->withTrashed()->first(),
            default => null,
        };
    }

    /** Libellé court de la cible pour les listes : nom du module, version, @compte. */
    public function targetLabel(): string
    {
        $target = $this->target();

        return match (true) {
            $target instanceof Module => $target->fullName(),
            $target instanceof ModuleVersion => $target->module->fullName().' '.$target->version,
            $target instanceof User => '@'.$target->handle,
            $target instanceof Review => __('moderation.review_of', ['handle' => '@'.$target->user->handle, 'module' => $target->module->fullName()]),
            default => __('moderation.target_gone'),
        };
    }
}
