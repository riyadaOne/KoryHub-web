<?php

namespace App\Enums;

/**
 * Événements du journal de sécurité (BACKEND-SCHEMA §7, F-AUT-19), dont ceux des jetons
 * du CLI (W02) : connexion d'un terminal, jeton créé, jeton révoqué.
 */
enum SecurityEventType: string implements HasStatusBadge
{
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case CliLogin = 'cli_login';
    case TokenCreated = 'token_created';
    case TokenRevoked = 'token_revoked';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case PasskeyAdded = 'passkey_added';
    case PasskeyRemoved = 'passkey_removed';
    case PasswordChanged = 'password_changed';
    case EmailChanged = 'email_changed';

    public function label(): string
    {
        return __("account.security_event.{$this->value}");
    }

    public function color(): StatusColor
    {
        return match ($this) {
            self::Login, self::CliLogin => StatusColor::Success,
            self::LoginFailed => StatusColor::Danger,
            self::TwoFactorDisabled, self::PasskeyRemoved, self::TokenRevoked => StatusColor::Warning,
            self::TwoFactorEnabled, self::PasskeyAdded, self::TokenCreated => StatusColor::Info,
            self::PasswordChanged, self::EmailChanged => StatusColor::Neutral,
        };
    }
}
