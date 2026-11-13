<?php

namespace App\Enums;

/**
 * Niveau d'abonnement à un module (F-RCH-09, BACKEND-SCHEMA §7) : toutes les activités (nouvelle
 * version, dépréciation, archivage), versions seulement, ou ignorer (aucune notification, même
 * « mise à jour disponible »).
 */
enum WatchLevel: string
{
    case All = 'all';
    case Releases = 'releases';
    case Ignore = 'ignore';

    public function label(): string
    {
        return __("community.watch.{$this->value}");
    }

    public function description(): string
    {
        return __("community.watch.{$this->value}_text");
    }
}
