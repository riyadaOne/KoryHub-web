<?php

namespace App\Support;

use App\Enums\Locale;

/**
 * Texte de gauche à droite (nom de module « @auteur/nom », version) inséré dans une phrase
 * d'une langue de droite à gauche : isolé par LRI … PDI pour que le « @ » reste en tête
 * (recette W06, arabe). Sans effet dans une langue de gauche à droite.
 */
final class Bidi
{
    public static function isolate(string $text): string
    {
        return Locale::current()->direction() === 'rtl' && $text !== ''
            ? "\u{2066}".$text."\u{2069}"
            : $text;
    }
}
