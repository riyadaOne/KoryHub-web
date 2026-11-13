<?php

namespace App\Models;

use App\Enums\WatchLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement à un module (F-RCH-09) : niveau Toutes les activités, Versions seulement ou Ignorer ;
 * préférence, sans suppression logique.
 *
 * @property string $id
 * @property string $user_id
 * @property string $module_id
 * @property WatchLevel $level
 * @property-read Module $module
 */
#[Fillable(['user_id', 'module_id', 'level'])]
class Watch extends Model
{
    use HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['level' => WatchLevel::class];
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
