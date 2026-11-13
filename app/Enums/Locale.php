<?php

namespace App\Enums;

/**
 * Langues de l'interface (DEM-04, NF-06). L'anglais est la langue par défaut.
 */
enum Locale: string
{
    case English = 'en';
    case French = 'fr';
    case Arabic = 'ar';
    case Portuguese = 'pt';

    public static function default(): self
    {
        return self::English;
    }

    /** Langue de la requête en cours. */
    public static function current(): self
    {
        return self::tryFrom(app()->getLocale()) ?? self::default();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Sens d'écriture : l'arabe s'écrit de droite à gauche (UXUI §9). */
    public function direction(): string
    {
        return $this === self::Arabic ? 'rtl' : 'ltr';
    }

    /** Nom de la langue dans la langue elle-même, pour le sélecteur. */
    public function nativeName(): string
    {
        return match ($this) {
            self::English => 'English',
            self::French => 'Français',
            self::Arabic => 'العربية',
            self::Portuguese => 'Português',
        };
    }
}
