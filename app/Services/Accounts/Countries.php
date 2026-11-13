<?php

namespace App\Services\Accounts;

use ResourceBundle;

/**
 * Pays du profil (F-AUT-16) : codes ISO 3166-1 alpha-2, noms dans la langue de l'interface,
 * lus dans les données ICU de l'extension intl. Le pays sert à la visibilité des talents,
 * jamais à une règle métier (PRD P2).
 */
class Countries
{
    /** Codes ICU qui ne sont pas des pays (zones, régions réservées). */
    private const NOT_COUNTRIES = ['EU', 'EZ', 'UN', 'QO', 'XA', 'XB', 'ZZ'];

    /** @return array<string, string> code => nom, triés par nom */
    public function options(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $countries = [];

        foreach ($this->codes() as $code) {
            $countries[$code] = \Locale::getDisplayRegion('-'.$code, $locale) ?: $code;
        }

        $collator = new \Collator($locale);
        uasort($countries, fn (string $a, string $b) => $collator->compare($a, $b));

        return $countries;
    }

    public function isValid(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    /** @return list<string> */
    public function codes(): array
    {
        static $codes = null;

        if ($codes === null) {
            $bundle = ResourceBundle::create('en', 'ICUDATA-region')->get('Countries');
            $codes = [];

            foreach ($bundle as $code => $name) {
                if (preg_match('/^[A-Z]{2}$/', (string) $code) === 1 && ! in_array($code, self::NOT_COUNTRIES, true)) {
                    $codes[] = (string) $code;
                }
            }
        }

        return $codes;
    }
}
