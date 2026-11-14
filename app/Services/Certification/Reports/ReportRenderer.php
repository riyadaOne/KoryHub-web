<?php

namespace App\Services\Certification\Reports;

/**
 * Produit le PDF d'une vue (TRD §15) ; remplacé par une version factice dans les tests, pour ne
 * pas lancer Chrome.
 */
interface ReportRenderer
{
    /** @param  array<string, mixed>  $data */
    public function render(string $view, array $data): string;
}
