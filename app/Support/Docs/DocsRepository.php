<?php

namespace App\Support\Docs;

use App\Enums\Locale;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Tempest\Highlight\CommonMark\HighlightExtension;
use Tempest\Highlight\Highlighter;

/**
 * Pages en Markdown par langue (W09, S-10 à S-13, décision 1a) : documentation dans
 * resources/docs/{langue}/{page}.md (ordre dans resources/docs/nav.php), pages publiques dans
 * resources/pages/{langue}/{page}.md. Une page absente dans la langue s'affiche en anglais (sinon en français).
 * Titre = premier titre « # » ; sommaire = titres « ## » ; code et chemins de gauche à droite.
 */
final class DocsRepository
{
    public function __construct(private string $root) {}

    public static function docs(): self
    {
        return new self(resource_path('docs'));
    }

    public static function publicPages(): self
    {
        return new self(resource_path('pages'));
    }

    /**
     * Navigation de la documentation : groupe => liste de pages (identifiants).
     *
     * @return array<string, list<string>>
     */
    public static function navigation(): array
    {
        return require resource_path('docs/nav.php');
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_merge(...array_values(self::navigation()));
    }

    public function find(Locale $locale, string $slug): ?DocPage
    {
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return null;
        }

        foreach (self::candidates($locale) as $candidate) {
            $path = $this->root.DIRECTORY_SEPARATOR.$candidate->value.DIRECTORY_SEPARATOR.$slug.'.md';
            if (File::exists($path)) {
                return $this->parse($slug, $candidate, File::get($path), $candidate !== $locale);
            }
        }

        return null;
    }

    /** Titre d'une page dans la langue (ou en anglais), sans la convertir. */
    public function title(Locale $locale, string $slug): ?string
    {
        foreach (self::candidates($locale) as $candidate) {
            $path = $this->root.DIRECTORY_SEPARATOR.$candidate->value.DIRECTORY_SEPARATOR.$slug.'.md';
            if (File::exists($path) && preg_match('/^# (.+)$/m', File::get($path), $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * Langue demandée, puis anglais, puis français (langue de rédaction, W09) : une page absente
     * dans la langue s'affiche dans la suivante, avec un bandeau.
     *
     * @return list<Locale>
     */
    private static function candidates(Locale $locale): array
    {
        return array_values(array_unique([$locale, Locale::English, Locale::French], SORT_REGULAR));
    }

    private function parse(string $slug, Locale $locale, string $markdown, bool $fallback): DocPage
    {
        $title = preg_match('/^# (.+)$/m', $markdown, $m) ? trim($m[1]) : $slug;
        $body = preg_replace('/^# .+$\R?/m', '', $markdown, 1);

        $html = (string) Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ], [new HighlightExtension(new Highlighter)]);

        // Sommaire : un identifiant par titre de section ; code et commandes de gauche à droite (UXUI §10).
        $headings = [];
        $html = preg_replace_callback('#<h2>(.*?)</h2>#s', function (array $match) use (&$headings) {
            $text = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5));
            $id = Str::slug($text) ?: 'section-'.(count($headings) + 1);
            $headings[] = ['id' => $id, 'text' => $text];

            return '<h2 id="'.e($id).'">'.$match[1].'</h2>';
        }, $html);
        $html = str_replace(['<pre>', '<pre ', '<code>'], ['<pre dir="ltr">', '<pre dir="ltr" ', '<code dir="ltr">'], $html);

        return new DocPage($slug, $locale, $title, new HtmlString($html), $headings, $fallback);
    }
}
