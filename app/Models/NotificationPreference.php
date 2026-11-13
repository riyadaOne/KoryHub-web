<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Préférence d'un compte pour un type de notification (S-43) ; sans ligne, les deux canaux
 * sont actifs.
 *
 * @property string $id
 * @property string $user_id
 * @property NotificationType $type
 * @property bool $mail
 * @property bool $database
 */
#[Fillable(['user_id', 'type', 'mail', 'database'])]
class NotificationPreference extends Model
{
    use HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => NotificationType::class, 'mail' => 'boolean', 'database' => 'boolean'];
    }
}
