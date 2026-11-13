<?php

namespace App\Enums;

/**
 * Type d'un composant de l'architecture (BACKEND-SCHEMA §7, UXUI §6.2) : sa forme dans le diagramme.
 */
enum ComponentKind: string
{
    case ClassType = 'class';
    case Interface = 'interface';
    case Trait = 'trait';
    case Enum = 'enum';
    case Function = 'function';
    case Module = 'module';

    public function label(): string
    {
        return __("modules.architecture.kinds.{$this->value}");
    }

    /** Forme Mermaid du nœud : rectangle, hexagone, parallélogramme… (libellé déjà échappé). */
    public function mermaidShape(string $id, string $label): string
    {
        return match ($this) {
            self::ClassType => "{$id}[\"{$label}\"]",
            self::Interface => "{$id}{{\"{$label}\"}}",
            self::Trait => "{$id}([\"{$label}\"])",
            self::Enum => "{$id}[/\"{$label}\"/]",
            self::Function => "{$id}(\"{$label}\")",
            self::Module => "{$id}[[\"{$label}\"]]",
        };
    }
}
