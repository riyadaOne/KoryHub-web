<?php

namespace Tests\Fakes;

use App\Services\Modules\Secrets\SecretFinding;
use App\Services\Modules\Secrets\SecretScanFailed;
use App\Services\Modules\Secrets\SecretScanner;

/**
 * Faux scanner de secrets (TRD §16) : signale toute ligne qui contient « SECRET= » ou un jeton
 * kory_, ou échoue sur demande ; gitleaks n'est pas lancé dans les tests.
 */
class FakeSecretScanner implements SecretScanner
{
    public bool $fail = false;

    public function scan(string $directory): array
    {
        if ($this->fail) {
            throw new SecretScanFailed('faux échec');
        }

        $findings = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $relative = ltrim(str_replace([$directory, '\\'], ['', '/'], $file->getPathname()), '/');
            foreach (file($file->getPathname()) ?: [] as $number => $line) {
                if (str_contains($line, 'SECRET=') || preg_match('/kory_[A-Za-z0-9]{40}[0-9a-f]{8}/', $line)) {
                    $findings[] = new SecretFinding($relative, $number + 1, 'generic-api-key');
                }
            }
        }

        return $findings;
    }
}
