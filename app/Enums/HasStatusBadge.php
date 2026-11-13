<?php

namespace App\Enums;

/**
 * Enum de statut affiché en badge (UXUI §8, BACKEND-SCHEMA §7) :
 * libellé traduit et couleur définis dans l'enum.
 */
interface HasStatusBadge
{
    /** Libellé traduit dans la langue de la requête. */
    public function label(): string;

    public function color(): StatusColor;
}
