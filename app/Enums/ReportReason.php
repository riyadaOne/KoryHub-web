<?php

namespace App\Enums;

/** Motif d'un signalement (BACKEND-SCHEMA §7, F-MOD-09). */
enum ReportReason: string
{
    case Malware = 'malware';
    case IllegalContent = 'illegal_content';
    case Copyright = 'copyright';
    case Harassment = 'harassment';

    public function label(): string
    {
        return __("moderation.reason.{$this->value}");
    }

    public function description(): string
    {
        return __("moderation.reason_help.{$this->value}");
    }
}
