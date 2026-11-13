<?php

namespace App\Enums;

/**
 * Lien entre deux composants (BACKEND-SCHEMA §7, UXUI §6.2) : son trait dans le diagramme.
 */
enum LinkKind: string
{
    case Uses = 'uses';
    case Implements = 'implements';
    case Extends = 'extends';
    case Calls = 'calls';

    public function label(): string
    {
        return __("modules.architecture.links.{$this->value}");
    }

    /** Flèche Mermaid : trait plein, pointillé ou épais, avec le libellé du lien. */
    public function mermaidArrow(string $label): string
    {
        return match ($this) {
            self::Uses => "-->|\"{$label}\"|",
            self::Implements, self::Extends => "-.->|\"{$label}\"|",
            self::Calls => "==>|\"{$label}\"|",
        };
    }
}
