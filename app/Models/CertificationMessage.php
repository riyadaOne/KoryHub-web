<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Échange pendant un complément (F-MOD-03, F-MOD-08) : demande de l'admin ou réponse du
 * développeur ; version_id = nouvelle version proposée en réponse.
 *
 * @property string $id
 * @property string $certification_id
 * @property string $author_id
 * @property string $body
 * @property string|null $version_id
 * @property Carbon $created_at
 * @property-read User $author
 * @property-read ModuleVersion|null $version
 */
#[Fillable(['certification_id', 'author_id', 'body', 'version_id'])]
class CertificationMessage extends Model
{
    use HasUuids;

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<ModuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ModuleVersion::class, 'version_id');
    }
}
