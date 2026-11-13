<?php

use App\Enums\Locale;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/*
 * Traductions complètes et aucun texte en dur (T-04, UXUI §13).
 */

/** @return array<string, list<string>> clés « fichier.clé » par langue */
function translationKeys(): array
{
    $keys = [];

    foreach (Locale::values() as $locale) {
        $keys[$locale] = [];

        foreach (File::files(lang_path($locale)) as $file) {
            $name = $file->getFilenameWithoutExtension();
            $flat = Arr::dot(require $file->getPathname());

            foreach (array_keys($flat) as $key) {
                $keys[$locale][] = "{$name}.{$key}";
            }
        }

        sort($keys[$locale]);
    }

    return $keys;
}

test('chaque langue a exactement les mêmes clés que l\'anglais', function () {
    $keys = translationKeys();

    foreach (Locale::values() as $locale) {
        expect(array_values(array_diff($keys['en'], $keys[$locale])))
            ->toBe([], "Clés absentes en {$locale}.")
            ->and(array_values(array_diff($keys[$locale], $keys['en'])))
            ->toBe([], "Clés en trop en {$locale}.");
    }
});

test('aucune traduction n\'est vide', function () {
    foreach (Locale::values() as $locale) {
        foreach (File::files(lang_path($locale)) as $file) {
            foreach (Arr::dot(require $file->getPathname()) as $key => $value) {
                expect($value)->not->toBe('', "{$locale}/{$file->getFilename()} : {$key} est vide.");
            }
        }
    }
});

/*
 * Vues de KoryHub contrôlées (les vues du kit sont reprises et traduites en W01).
 * Après retrait des commentaires, des balises, des instructions Blade et des {{ }},
 * il ne doit rester aucun mot.
 */
test('les vues de KoryHub ne contiennent aucun texte en dur', function () {
    $folders = ['admin', 'docs', 'public'];
    $files = [
        'layouts/public.blade.php', 'layouts/admin.blade.php', 'layouts/app.blade.php',
        'components/app-logo.blade.php', 'components/app-logo-icon.blade.php',
        'components/relative-date.blade.php', 'components/status-badge.blade.php',
        'components/copy-command.blade.php', 'components/empty-state.blade.php',
        'components/warning-banner.blade.php', 'components/confirm-step.blade.php',
    ];

    $paths = collect($folders)
        ->flatMap(fn (string $folder) => File::allFiles(resource_path("views/{$folder}")))
        ->map(fn ($file) => $file->getPathname())
        ->merge(array_map(fn (string $file) => resource_path("views/{$file}"), $files));

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        $text = File::get($path);
        $text = preg_replace('/\{\{--.*?--\}\}/s', ' ', $text);
        $text = preg_replace('/@php.*?@endphp/s', ' ', $text);
        $text = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', ' ', $text);
        // Balises : les attributs entre guillemets peuvent contenir « -> » (auth()->user()).
        $text = preg_replace('/<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/s', ' ', $text);
        $text = preg_replace('/@\w+(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', ' ', $text);
        // Mots autorisés : unité de temps et nom de la marque (jamais traduits).
        $text = trim(preg_replace('/\b(UTC|Kory|Hub)\b/', ' ', $text));

        expect(preg_match('/\p{L}{2,}/u', $text))->toBe(0, "Texte en dur dans {$path} : {$text}");
    }
});
