<?php

namespace App\Enums;

/**
 * Statut d'un signalement (BACKEND-SCHEMA §6, §7, F-MOD-10) : ouvert → en examen (admin) → clos
 * sans suite ou clos avec action (admin). Un signalement clos ne se rouvre jamais.
 */
enum ReportStatus: string implements HasStatusBadge
{
    case Open = 'open';
    case InReview = 'in_review';
    case ClosedNoAction = 'closed_no_action';
    case ClosedWithAction = 'closed_with_action';

    public function label(): string
    {
        return __("moderation.status.{$this->value}");
    }

    public function color(): StatusColor
    {
        return match ($this) {
            self::Open => StatusColor::Warning,
            self::InReview => StatusColor::Info,
            self::ClosedNoAction, self::ClosedWithAction => StatusColor::Neutral,
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Open => [self::InReview],
            self::InReview => [self::ClosedNoAction, self::ClosedWithAction],
            default => [],
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::InReview;
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Open, self::InReview];
    }
}
