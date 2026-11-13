<?php

namespace App\Enums;

/**
 * Les 5 familles de couleur des statuts (UXUI §8). La couleur d'un statut est
 * définie dans son enum, jamais dans la vue.
 */
enum StatusColor: string
{
    case Neutral = 'neutral';
    case Info = 'info';
    case Warning = 'warning';
    case Success = 'success';
    case Danger = 'danger';

    /** Couleur du composant badge de Flux (palette Tailwind). */
    public function fluxColor(): string
    {
        return match ($this) {
            self::Neutral => 'zinc',
            self::Info => 'blue',
            self::Warning => 'amber',
            self::Success => 'green',
            self::Danger => 'red',
        };
    }
}
