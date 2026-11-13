<?php

namespace App\Enums;

/**
 * Auteur d'un événement d'audit (BACKEND-SCHEMA §7) : un compte, ou « Système »
 * pour les automatismes (PRD §7, UXUI §13).
 */
enum ActorType: string
{
    case User = 'user';
    case System = 'system';
}
