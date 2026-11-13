<?php

namespace App\Services\Modules\Secrets;

/** Secret trouvé : fichier (chemin relatif au kory), ligne et type de règle ; jamais la valeur. */
final readonly class SecretFinding
{
    public function __construct(
        public string $file,
        public int $line,
        public string $rule,
    ) {}
}
