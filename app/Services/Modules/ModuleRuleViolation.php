<?php

namespace App\Services\Modules;

use DomainException;

/**
 * Règle du cycle de vie refusée (suppression après 72 h, module public, passage en privé) ;
 * message traduit à partir de sa clé.
 */
class ModuleRuleViolation extends DomainException
{
    public function __construct(public readonly string $key)
    {
        parent::__construct(__($key));
    }
}
