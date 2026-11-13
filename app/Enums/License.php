<?php

namespace App\Enums;

/**
 * Licences proposées (F-REG-11, BACKEND-SCHEMA §7) ; libellé = identifiant SPDX, MIT par défaut.
 */
enum License: string
{
    case Mit = 'mit';
    case Apache20 = 'apache_2_0';
    case Bsd2Clause = 'bsd_2_clause';
    case Bsd3Clause = 'bsd_3_clause';
    case Isc = 'isc';
    case Mpl20 = 'mpl_2_0';
    case Gpl20 = 'gpl_2_0';
    case Gpl30 = 'gpl_3_0';
    case Lgpl30 = 'lgpl_3_0';
    case Agpl30 = 'agpl_3_0';
    case Unlicense = 'unlicense';

    /** Identifiant SPDX, tel qu'écrit dans kory.json et affiché. */
    public function spdx(): string
    {
        return match ($this) {
            self::Mit => 'MIT',
            self::Apache20 => 'Apache-2.0',
            self::Bsd2Clause => 'BSD-2-Clause',
            self::Bsd3Clause => 'BSD-3-Clause',
            self::Isc => 'ISC',
            self::Mpl20 => 'MPL-2.0',
            self::Gpl20 => 'GPL-2.0',
            self::Gpl30 => 'GPL-3.0',
            self::Lgpl30 => 'LGPL-3.0',
            self::Agpl30 => 'AGPL-3.0',
            self::Unlicense => 'Unlicense',
        };
    }

    /** Licence à partir de son identifiant SPDX (casse indifférente), ou null. */
    public static function fromSpdx(string $spdx): ?self
    {
        foreach (self::cases() as $license) {
            if (strcasecmp($license->spdx(), $spdx) === 0) {
                return $license;
            }
        }

        return null;
    }
}
