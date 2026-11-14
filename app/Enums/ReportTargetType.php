<?php

namespace App\Enums;

/** Objet visé par un signalement (BACKEND-SCHEMA §7, F-MOD-09). */
enum ReportTargetType: string
{
    case Module = 'module';
    case Version = 'version';
    case User = 'user';
    case Review = 'review';

    public function label(): string
    {
        return __("moderation.target.{$this->value}");
    }
}
