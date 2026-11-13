<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lien privé entre un compte et une version qu'il a téléchargée (BACKEND-SCHEMA §4.3, F-RCH-14) :
 * lu seulement pour l'intéressé (S-46), les notes vérifiées et les alertes ; effacé avec le compte.
 *
 * @property string $id
 * @property string $user_id
 * @property string $module_id
 * @property string $version_id
 * @property Carbon $first_at
 * @property Carbon $last_at
 * @property int $count
 * @property-read Module $module
 * @property-read ModuleVersion $version
 */
#[Fillable(['user_id', 'module_id', 'version_id', 'first_at', 'last_at', 'count'])]
class ModuleDownload extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['first_at' => 'datetime', 'last_at' => 'datetime', 'count' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class)->withTrashed();
    }

    /** @return BelongsTo<ModuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ModuleVersion::class, 'version_id')->withTrashed();
    }
}
