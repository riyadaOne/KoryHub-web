<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Heure exacte affichée dans l'administration (S-71, S-72, recette W08, option a) : les dates sont
 * stockées en UTC et montrées dans le fuseau de l'exploitation (koryhub.display_timezone,
 * Africa/Ndjamena par défaut), avec l'abréviation du fuseau (WAT) pour lever toute ambiguïté.
 */
final class LocalTime
{
    public static function format(DateTimeInterface $at, string $pattern = 'd/m/Y H:i'): string
    {
        return Carbon::instance($at)->setTimezone((string) config('koryhub.display_timezone', 'UTC'))->format($pattern.' T');
    }
}
