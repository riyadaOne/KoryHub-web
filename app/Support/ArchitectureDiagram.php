<?php

namespace App\Support;

use App\Models\ModuleVersion;

/**
 * Texte Mermaid de l'architecture d'une version (S-14, TRD §22) : construit par le serveur à partir
 * des tables, avec des identifiants générés (n1, n2…) et des libellés échappés ; Mermaid est rendu
 * en mode strict et les clics sont gérés par notre script (resources/js/architecture.js).
 */
final class ArchitectureDiagram
{
    /**
     * @return array{text: string, files: array<string, string>, lines: list<array{from: string, kind: string, to: string, from_file: string, to_file: string}>}
     */
    public static function build(ModuleVersion $version): array
    {
        $components = $version->components()->with('file')->get();
        $links = $version->links()->with(['from', 'to'])->get();

        $ids = [];
        $files = [];
        $text = ['flowchart LR'];

        foreach ($components->values() as $index => $component) {
            $id = 'n'.($index + 1);
            $ids[$component->id] = $id;
            $files[$id] = $component->file->path;
            $text[] = '    '.$component->kind->mermaidShape($id, self::escape($component->key));
        }

        $lines = [];
        foreach ($links as $link) {
            $text[] = '    '.$ids[$link->from_component_id].' '.$link->kind->mermaidArrow(self::escape($link->kind->label())).' '.$ids[$link->to_component_id];
            $lines[] = [
                'from' => $link->from->key,
                'kind' => $link->kind->label(),
                'to' => $link->to->key,
                'from_file' => $files[$ids[$link->from_component_id]],
                'to_file' => $files[$ids[$link->to_component_id]],
            ];
        }

        return ['text' => implode("\n", $text), 'files' => $files, 'lines' => $lines];
    }

    /** Libellé sûr dans un texte Mermaid entre guillemets : seuls lettres, chiffres et ponctuation simple restent. */
    public static function escape(string $label): string
    {
        return (string) preg_replace('/[^\p{L}\p{N} _.\-·]/u', '', $label);
    }
}
