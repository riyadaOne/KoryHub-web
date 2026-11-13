<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Compte KoryHub (BACKEND-SCHEMA §4.1). Pas de suppression logique : un compte supprimé
 * garde sa ligne au statut « deleted » (données personnelles effacées, nom @auteur réservé).
 *
 * @property string $id
 * @property string $handle
 * @property string|null $name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property Role $role
 * @property AccountStatus $status
 * @property Carbon|null $suspended_at
 * @property string|null $suspension_reason
 * @property Carbon|null $erased_at
 * @property string $locale
 * @property string|null $country
 * @property string|null $avatar_path
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['handle', 'name', 'email', 'password', 'locale', 'country', 'avatar_path'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Valeurs par défaut, identiques à celles de la base, pour qu'un compte tout juste créé
     * soit actif dès la requête en cours (sinon EnsureAccountActive le déconnecterait).
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'role' => 'developer',
        'status' => 'active',
        'locale' => 'en',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'status' => AccountStatus::class,
            'suspended_at' => 'datetime',
            'erased_at' => 'datetime',
        ];
    }

    /** @return HasMany<SecurityEvent, $this> */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    /** Adresse de la photo de profil, ou null pour afficher les initiales. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null ? null : Storage::disk(config('koryhub.avatars.disk'))->url($this->avatar_path);
    }

    /** Nom affiché : le nom saisi, sinon le nom @auteur. */
    public function displayName(): string
    {
        return $this->name ?: '@'.$this->handle;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->displayName(), true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : Str::upper(Str::substr(ltrim($this->displayName(), '@'), 0, 2));
    }

    /**
     * Langue des e-mails envoyés à ce compte (TRD §19).
     */
    public function preferredLocale(): string
    {
        return $this->locale;
    }
}
