<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Avis d'un développeur sur un module (F-RCH-07, F-RCH-17, BACKEND-SCHEMA §4.3) : une note de 1 à
 * 5 et un commentaire facultatif, un seul par développeur et par module ; supprimé logiquement et
 * restauré s'il note à nouveau. « Vérifié » : il a téléchargé le module en étant connecté.
 *
 * @property string $id
 * @property string $module_id
 * @property string $user_id
 * @property int $rating
 * @property string|null $comment
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Module $module
 * @property-read User $user
 */
#[Fillable(['module_id', 'user_id', 'rating', 'comment'])]
class Review extends Model
{
    use HasUuids, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Note vérifiée : le compte a téléchargé une version du module en étant connecté. */
    public function isVerified(): bool
    {
        return ModuleDownload::query()->where('user_id', $this->user_id)->where('module_id', $this->module_id)->exists();
    }

    /** Avis modifié après sa publication. */
    public function wasEdited(): bool
    {
        return $this->updated_at->gt($this->created_at->addSecond());
    }
}
