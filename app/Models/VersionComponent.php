<?php

namespace App\Models;

use App\Enums\ComponentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Composant de l'architecture d'une version (F-REG-22) : nom, type, fichier ; jamais modifié.
 *
 * @property string $id
 * @property string $version_id
 * @property string $key
 * @property ComponentKind $kind
 * @property string $file_id
 * @property int $position
 * @property-read VersionFile $file
 */
#[Fillable(['version_id', 'key', 'kind', 'file_id', 'position'])]
class VersionComponent extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => ComponentKind::class, 'position' => 'integer'];
    }

    /** @return BelongsTo<VersionFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(VersionFile::class, 'file_id');
    }
}
