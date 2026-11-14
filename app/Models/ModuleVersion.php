<?php

namespace App\Models;

use App\Enums\CertificationStatus;
use App\Enums\License;
use App\Enums\VersionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Version d'un module (BACKEND-SCHEMA §4.2) : les champs du manifeste sont copiés à la
 * publication et jamais réécrits (F-REG-10) ; seules les colonnes de statut changent ensuite.
 * Écrite par App\Services\Modules\VersionPublisher, puis par VersionService.
 *
 * @property string $id
 * @property string $module_id
 * @property string $version
 * @property int $major
 * @property int $minor
 * @property int $patch
 * @property string|null $prerelease
 * @property VersionStatus $status
 * @property string|null $version_key
 * @property string|null $description
 * @property License $license
 * @property string|null $language
 * @property string|null $readme
 * @property string|null $archive_path
 * @property string $archive_sha256
 * @property int $archive_size
 * @property int $files_count
 * @property string $published_by
 * @property Carbon $submitted_at
 * @property Carbon|null $published_at
 * @property string|null $rejection_code
 * @property string|null $rejection_reason
 * @property Carbon|null $deprecated_at
 * @property string|null $deprecation_message
 * @property Carbon|null $at_risk_at
 * @property string|null $at_risk_message
 * @property string|null $at_risk_report_id
 * @property Carbon|null $removed_at
 * @property string|null $removed_by
 * @property string|null $removed_reason
 * @property int $download_count
 * @property Carbon|null $first_downloaded_at
 * @property-read Module $module
 */
#[Fillable([
    'module_id', 'version', 'major', 'minor', 'patch', 'prerelease', 'status', 'description', 'license',
    'language', 'readme', 'archive_path', 'archive_sha256', 'archive_size', 'files_count', 'published_by',
    'submitted_at', 'published_at', 'rejection_code', 'rejection_reason', 'deprecated_at', 'deprecation_message',
])]
class ModuleVersion extends Model
{
    use HasUuids, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => VersionStatus::class,
            'license' => License::class,
            'major' => 'integer',
            'minor' => 'integer',
            'patch' => 'integer',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'deprecated_at' => 'datetime',
            'at_risk_at' => 'datetime',
            'removed_at' => 'datetime',
            'download_count' => 'integer',
            'first_downloaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<VersionFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(VersionFile::class, 'version_id');
    }

    /** @return HasMany<VersionComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(VersionComponent::class, 'version_id')->orderBy('position');
    }

    /** @return HasMany<VersionComponentLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(VersionComponentLink::class, 'version_id');
    }

    /** @return HasMany<VersionRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(VersionRequirement::class, 'version_id')->orderBy('ecosystem')->orderBy('name');
    }

    /**
     * Dossier accordé à cette version (badge « Vérifié en Prod », F-MOD-04) ; une révocation l'en retire.
     *
     * @return HasOne<Certification, $this>
     */
    public function grantedCertification(): HasOne
    {
        return $this->hasOne(Certification::class, 'version_id')->where('status', CertificationStatus::Granted);
    }

    public function isCertified(): bool
    {
        return $this->grantedCertification !== null;
    }

    public function isPrerelease(): bool
    {
        return $this->prerelease !== null;
    }
}
