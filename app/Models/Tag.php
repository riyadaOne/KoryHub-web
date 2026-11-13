<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Étiquette libre (F-REG-14), en minuscules, partagée entre les modules.
 *
 * @property string $id
 * @property string $name
 */
#[Fillable(['name'])]
class Tag extends Model
{
    use HasUuids, SoftDeletes;

    /** @return BelongsToMany<Module, $this> */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class);
    }
}
