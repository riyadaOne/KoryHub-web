<?php

namespace App\Enums;

/** Action prise à la clôture d'un signalement (BACKEND-SCHEMA §7, F-MOD-10). */
enum ReportResolution: string
{
    case SecurityWarning = 'security_warning';
    case LegalRemoval = 'legal_removal';
    case ReviewHidden = 'review_hidden';
    case AccountSuspended = 'account_suspended';
    case AccountDeleted = 'account_deleted';

    public function label(): string
    {
        return __("moderation.resolution.{$this->value}");
    }
}
