<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Résultat d'un contrôle préliminaire (F-MOD-02, F-MOD-17) ; ajout seul.
 *
 * @property string $id
 * @property string $certification_id
 * @property string $version_id
 * @property string $code
 * @property bool $passed
 * @property string|null $detail
 * @property Carbon $checked_at
 */
#[Fillable(['certification_id', 'version_id', 'code', 'passed', 'detail', 'checked_at'])]
class CertificationCheck extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['passed' => 'boolean', 'checked_at' => 'datetime'];
    }
}
