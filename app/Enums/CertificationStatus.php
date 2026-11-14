<?php

namespace App\Enums;

/**
 * Statut d'un dossier de certification (BACKEND-SCHEMA §6, §7, APPFLOW §5.4) : demandée → en
 * audit (admin) → complément requis (admin) → en audit (réponse du développeur) ; en audit →
 * accordée ou refusée (admin) ; accordée → révoquée (admin).
 */
enum CertificationStatus: string implements HasStatusBadge
{
    case Requested = 'requested';
    case InAudit = 'in_audit';
    case ComplementRequired = 'complement_required';
    case Granted = 'granted';
    case Refused = 'refused';
    case Revoked = 'revoked';

    public function label(): string
    {
        return __("certification.status.{$this->value}");
    }

    public function color(): StatusColor
    {
        return match ($this) {
            self::Requested, self::ComplementRequired => StatusColor::Warning,
            self::InAudit => StatusColor::Info,
            self::Granted => StatusColor::Success,
            self::Refused, self::Revoked => StatusColor::Danger,
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested => [self::InAudit],
            self::InAudit => [self::ComplementRequired, self::Granted, self::Refused],
            self::ComplementRequired => [self::InAudit],
            self::Granted => [self::Revoked],
            default => [],
        };
    }

    /** Dossier encore ouvert : un seul par module (BACKEND-SCHEMA §8). */
    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::InAudit, self::ComplementRequired], true);
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Requested, self::InAudit, self::ComplementRequired];
    }
}
