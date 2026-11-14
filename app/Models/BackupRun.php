<?php

namespace App\Models;

use App\Enums\BackupKind;
use App\Enums\BackupStatus;
use App\Exceptions\InvalidTransition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Une sauvegarde ou un test de restauration (F-ADM-08, BACKEND-SCHEMA §4.6) ; écrite par
 * App\Services\Operations\BackupService. Une sauvegarde terminée ne repasse jamais « en cours ».
 *
 * @property string $id
 * @property BackupKind $kind
 * @property BackupStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int|null $size
 * @property string|null $location
 * @property string|null $note
 */
#[Fillable(['kind', 'status', 'started_at', 'finished_at', 'size', 'location', 'note'])]
class BackupRun extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => BackupKind::class,
            'status' => BackupStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    /** Termine la sauvegarde ; refus si elle l'est déjà. */
    public function finish(BackupStatus $status, array $attributes = []): void
    {
        if (! in_array($status, $this->status->allowedNext(), true)) {
            throw new InvalidTransition($this->status, $status);
        }
        $this->forceFill(['status' => $status, 'finished_at' => now(), ...$attributes])->save();
    }

    /** Durée en secondes, ou null tant qu'elle tourne. */
    public function duration(): ?int
    {
        return $this->finished_at === null ? null : (int) $this->started_at->diffInSeconds($this->finished_at, true);
    }
}
