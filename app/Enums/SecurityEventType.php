<?php

namespace App\Enums;

/**
 * Événements du journal de sécurité (BACKEND-SCHEMA §7, F-AUT-19). Les types liés aux
 * jetons du CLI (cli_login, token_created, token_revoked) s'ajoutent en W02.
 */
enum SecurityEventType: string implements HasStatusBadge
{
    case Login = 'login';
    case LoginFailed = 'login_failed';
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
            self::Login => StatusColor::Success,
            self::LoginFailed => StatusColor::Danger,
            self::TwoFactorDisabled, self::PasskeyRemoved => StatusColor::Warning,
            self::TwoFactorEnabled, self::PasskeyAdded => StatusColor::Info,
            self::PasswordChanged, self::EmailChanged => StatusColor::Neutral,
        };
    }
}
