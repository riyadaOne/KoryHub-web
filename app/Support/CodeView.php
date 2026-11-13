<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Tempest\Highlight\CommonMark\HighlightExtension;
use Tempest\Highlight\Highlighter;

/**
 * Affichage du code et du README (UXUI §6.2, TRD §22) : coloration côté serveur par
 * tempest/highlight ; README en Markdown sans HTML brut ni lien dangereux (javascript:, data:).
 */
final class CodeView
{
    /** Langage de coloration d'après l'extension, ou null (texte brut). */
    private const LANGUAGES = [
        'php' => 'php', 'js' => 'javascript', 'mjs' => 'javascript', 'ts' => 'typescript', 'json' => 'json',
        'html' => 'html', 'blade.php' => 'blade', 'css' => 'css', 'sql' => 'sql', 'xml' => 'xml', 'yaml' => 'yaml',
        'yml' => 'yaml', 'md' => 'markdown', 'py' => 'python', 'go' => 'go', 'java' => 'java', 'kt' => 'kotlin',
        'dart' => 'dart', 'sh' => 'bash',
    ];

    /** Code coloré (HTML échappé par la bibliothèque) ; texte échappé si le langage est inconnu. */
    public static function highlight(string $path, string $code): HtmlString
    {
        $language = str_ends_with($path, '.blade.php') ? 'blade' : (self::LANGUAGES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null);

        if ($language === null) {
            return new HtmlString(e($code));
        }

        return new HtmlString((new Highlighter)->parse($code, $language));
    }

    /** README rendu sans HTML brut (supprimé) ni lien non sûr ; blocs de code colorés. */
    public static function markdown(?string $readme): HtmlString
    {
        if ($readme === null || trim($readme) === '') {
            return new HtmlString('');
        }

        return new HtmlString(Str::markdown($readme, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ], [new HighlightExtension(new Highlighter)]));
    }
}
