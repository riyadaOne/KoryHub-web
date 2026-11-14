<?php

namespace App\Support\Docs;

use App\Enums\Locale;
use Illuminate\Support\HtmlString;

/** Une page de la documentation ou une page publique, prête à afficher (W09). */
final readonly class DocPage
{
    /**
     * @param  list<array{id: string, text: string}>  $headings  sommaire (titres « ## »)
     * @param  bool  $fallback  page affichée en anglais faute de traduction
     */
    public function __construct(
        public string $slug,
        public Locale $locale,
        public string $title,
        public HtmlString $html,
        public array $headings,
        public bool $fallback,
    ) {}
}
