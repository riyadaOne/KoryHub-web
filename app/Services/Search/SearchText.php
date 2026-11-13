<?php

namespace App\Services\Search;

use App\Models\Module;

/**
 * Texte cherchable d'un module (W05.2, F-RCH-02) : README de la dernière version stable sans la
 * syntaxe Markdown, étiquettes, nom de la catégorie dans les 4 langues. Recalculé à chaque
 * publication et à chaque changement de réglages : l'indexation est automatique.
 */
class SearchText
{
    /** Taille gardée du README : le début suffit à la recherche. */
    private const MAX_README = 20000;

    public function refresh(Module $module): void
    {
        $module->loadMissing(['tags', 'category.translations', 'latestVersion']);

        $readme = (string) $module->latestVersion?->readme;
        $readme = preg_replace('/```.*?```/s', ' ', $readme) ?? '';
        $readme = preg_replace('/[#>*_`\[\]()!|~-]+/', ' ', $readme) ?? '';
        $readme = mb_substr(preg_replace('/\s+/u', ' ', $readme) ?? '', 0, self::MAX_README);

        $parts = [
            $module->tags->pluck('name')->implode(' '),
            $module->category?->translations->pluck('name')->implode(' '),
            $module->language,
            $readme,
        ];

        $module->forceFill(['search_text' => trim(implode(' ', array_filter($parts)))])->saveQuietly();
    }
}
