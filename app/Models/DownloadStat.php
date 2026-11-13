<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Téléchargements d'une version par jour (BACKEND-SCHEMA §4.3) : compteur anonyme, une ligne par
 * version et par jour, pour le score de popularité sur 90 jours (TRD §14, W05).
 *
 * @property string $id
 * @property string $module_id
 * @property string $version_id
 * @property Carbon $day
 * @property int $count
 */
#[Fillable(['module_id', 'version_id', 'day', 'count'])]
class DownloadStat extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['day' => 'date', 'count' => 'integer'];
    }
}
