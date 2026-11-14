<?php

namespace App\Models;

use App\Enums\CertificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Dossier de certification (BACKEND-SCHEMA §4.5, F-MOD-01 à F-MOD-05) : porte sur une version
 * précise ; la version peut changer pendant un complément (changement journalisé).
 *
 * @property string $id
 * @property string $module_id
 * @property string $version_id
 * @property string $requested_by
 * @property CertificationStatus $status
 * @property string|null $assigned_to
 * @property Carbon $requested_at
 * @property Carbon|null $audit_started_at
 * @property Carbon|null $complement_requested_at
 * @property Carbon|null $last_reminder_at
 * @property Carbon|null $decided_at
 * @property string|null $decided_by
 * @property string|null $decision_reason
 * @property string|null $architecture_comment
 * @property string|null $documentation_comment
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_by
 * @property string|null $revoked_reason
 * @property Carbon $updated_at
 * @property-read Module $module
 * @property-read ModuleVersion $version
 * @property-read User $requester
 * @property-read User|null $assignee
 */
#[Fillable(['module_id', 'version_id', 'requested_by', 'status', 'requested_at'])]
class Certification extends Model
{
    use HasUuids, SoftDeletes;

    /** @var array<string, string> */
    protected $attributes = ['status' => 'requested'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CertificationStatus::class,
            'requested_at' => 'datetime',
            'audit_started_at' => 'datetime',
            'complement_requested_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'decided_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** @return BelongsTo<ModuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ModuleVersion::class, 'version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<CertificationCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(CertificationCheck::class);
    }

    /** @return HasMany<CertificationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(CertificationMessage::class);
    }

    /** @return HasMany<CertificationReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(CertificationReport::class);
    }

    /** Dernière étape datée du dossier (S-36). */
    public function lastStepAt(): Carbon
    {
        return $this->updated_at;
    }
}
