<?php

namespace App\Enums;

/**
 * Types de notification (BACKEND-SCHEMA §7, TRD §19). Les types des modules suivants (nouvelle
 * version suivie, certification, signalement…) s'ajoutent avec leur module. Les types de
 * sécurité sont toujours envoyés ; les autres se règlent sur S-43.
 */
enum NotificationType: string
{
    case VersionPublished = 'version_published';
    case VersionRejected = 'version_rejected';
    case UpdateAvailable = 'update_available';
    case VersionDeprecated = 'version_deprecated';
    case CliLogin = 'cli_login';
    case TokenCreated = 'token_created';
    case TokenRevoked = 'token_revoked';
    case TokenExpiring = 'token_expiring';
    case PasswordChanged = 'password_changed';

    public function isSecurity(): bool
    {
        return in_array($this, [self::CliLogin, self::TokenCreated, self::TokenRevoked, self::TokenExpiring, self::PasswordChanged], true);
    }

    /** Types réglables sur S-43, par groupe affiché. */
    public static function adjustable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => ! $type->isSecurity()));
    }

    /** Kory installés : envoyées aux comptes qui ont téléchargé le module (F-INT-06), groupe à part sur S-43. */
    public function isForDownloaders(): bool
    {
        return in_array($this, [self::UpdateAvailable, self::VersionDeprecated], true);
    }

    /** Canal actif quand le compte n'a rien réglé : « mise à jour disponible » seulement dans la cloche. */
    public function enabledByDefault(string $channel): bool
    {
        return ! ($this === self::UpdateAvailable && $channel === 'mail');
    }

    public function label(): string
    {
        return __("notifications.types.{$this->value}");
    }
}
