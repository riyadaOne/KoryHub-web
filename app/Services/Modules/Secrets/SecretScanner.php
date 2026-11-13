<?php

namespace App\Services\Modules\Secrets;

/**
 * Recherche de secrets sur les fichiers d'une version (F-REG-07, TRD §16, §22). Implémentation :
 * gitleaks (GitleaksScanner) ; dans les tests, un faux scanner (Tests\Fakes\FakeSecretScanner).
 */
interface SecretScanner
{
    /**
     * @return list<SecretFinding> secrets trouvés, sans leur valeur
     *
     * @throws SecretScanFailed si la recherche n'a pas pu aboutir
     */
    public function scan(string $directory): array;
}
