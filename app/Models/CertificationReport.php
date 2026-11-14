<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rapport PDF d'une certification accordée, dans une langue (F-MOD-06) ; jamais réécrit.
 *
 * @property string $id
 * @property string $certification_id
 * @property string $locale
 * @property string $path
 * @property string $sha256
 * @property Carbon $generated_at
 * @property-read Certification $certification
 */
#[Fillable(['certification_id', 'locale', 'path', 'sha256', 'generated_at'])]
class CertificationReport extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    /** @return BelongsTo<Certification, $this> */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }
}
