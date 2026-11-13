<?php

namespace App\Enums;

/**
 * Rôle d'un compte (PRD §5.1, BACKEND-SCHEMA §7). Un compte devient admin seulement
 * par la commande php artisan koryhub:admin, jamais depuis un écran.
 */
enum Role: string
{
    case Developer = 'developer';
    case Admin = 'admin';
}
