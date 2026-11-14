<?php

namespace App\Services\Certification;

use RuntimeException;

/**
 * Demande ou réponse refusée : contrôles préliminaires échoués (liste des résultats) ou règle
 * non respectée (clé de traduction). Aucun dossier n'est créé ni modifié.
 */
class CertificationRefused extends RuntimeException
{
    /**
     * @param  list<array{code: string, passed: bool, detail: ?string}>  $checks
     */
    public function __construct(string $key, public readonly array $checks = [])
    {
        parent::__construct(__($key));
    }
}
