<?php

namespace App\Services\Certification\Reports;

use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Rendu par spatie/laravel-pdf : pilote réglé par LARAVEL_PDF_DRIVER (chrome en développement,
 * décision W06 1a) ; A4, marges du rapport (UXUI §11).
 */
class ChromeReportRenderer implements ReportRenderer
{
    public function render(string $view, array $data): string
    {
        return base64_decode(Pdf::view($view, $data)->format('a4')->margins(18, 16, 18, 16)->base64());
    }
}
