<?php

namespace App\Models;

use App\Enums\DeviceCodeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Code de connexion du CLI (TRD §7.3, BACKEND-SCHEMA §4.1) : le code de l'appareil n'est
 * gardé que haché ; le code affiché (ABCD-EFGH) est valable 15 minutes, une seule fois.
 * Écrit seulement par App\Services\Cli\DeviceAuthorizationService.
 *
 * @property string $id
 * @property string $device_code_hash
 * @property string $user_code
 * @property DeviceCodeStatus $status
 * @property string|null $user_id
 * @property int|null $token_lifetime_days
 * @property string|null $token_id
 * @property string|null $client_info
 * @property int $attempts
 * @property Carbon|null $last_polled_at
 * @property Carbon $expires_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $consumed_at
 */
#[Fillable([
    'device_code_hash', 'user_code', 'status', 'user_id', 'token_lifetime_days', 'token_id',
    'client_info', 'attempts', 'last_polled_at', 'expires_at', 'approved_at', 'consumed_at',
])]
class CliDeviceCode extends Model
{
    use HasUuids;

    /** @var array<string, string> */
    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => DeviceCodeStatus::class,
            'last_polled_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PersonalAccessToken, $this> */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
