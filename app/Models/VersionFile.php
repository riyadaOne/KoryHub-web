<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fichier d'une version publiée (TRD §13) : chemin relatif au kory, taille, empreinte SHA-256
 * des octets exacts. Jamais modifié.
 *
 * @property string $id
 * @property string $version_id
 * @property string $path
 * @property string $path_hash
 * @property int $size
 * @property string $sha256
 * @property bool $is_image
 */
#[Fillable(['version_id', 'path', 'path_hash', 'size', 'sha256', 'is_image'])]
class VersionFile extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['size' => 'integer', 'is_image' => 'boolean'];
    }

    /** @return BelongsTo<ModuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ModuleVersion::class, 'version_id');
    }
}
