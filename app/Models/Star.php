<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Favori (F-RCH-09) : préférence, sans suppression logique.
 *
 * @property string $id
 * @property string $user_id
 * @property string $module_id
 * @property-read Module $module
 */
#[Fillable(['user_id', 'module_id'])]
class Star extends Model
{
    use HasUuids;

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
