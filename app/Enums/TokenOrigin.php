<?php

namespace App\Enums;

/**
 * Origine d'un jeton (BACKEND-SCHEMA §7) : créé sur le site (S-42) ou remis à un terminal
 * par kory login (S-26).
 */
enum TokenOrigin: string
{
    case Site = 'site';
    case CliLogin = 'cli_login';

    public function label(): string
    {
        return __("tokens.origin.{$this->value}");
    }
}
