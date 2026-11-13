<?php

namespace App\Models;

use App\Enums\LinkKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lien entre deux composants d'une même version (F-REG-22) ; jamais modifié.
 *
 * @property string $id
 * @property string $version_id
 * @property string $from_component_id
 * @property string $to_component_id
 * @property LinkKind $kind
 * @property-read VersionComponent $from
 * @property-read VersionComponent $to
 */
#[Fillable(['version_id', 'from_component_id', 'to_component_id', 'kind'])]
class VersionComponentLink extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => LinkKind::class];
    }

    /** @return BelongsTo<VersionComponent, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(VersionComponent::class, 'from_component_id');
    }

    /** @return BelongsTo<VersionComponent, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(VersionComponent::class, 'to_component_id');
    }
}
