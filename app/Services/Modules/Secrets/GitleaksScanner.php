<?php

namespace App\Services\Modules\Secrets;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Recherche de secrets par gitleaks (TRD §22) : règles par défaut plus la règle des jetons
 * kory_ (resources/gitleaks/koryhub.toml), sur le dossier temporaire de la version, avec une
 * durée maximale. Valeurs masquées (--redact) : le rapport ne contient jamais le secret.
 */
class GitleaksScanner implements SecretScanner
{
    public function __construct(
        private string $binary,
        private int $timeoutSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('koryhub.publication.gitleaks_binary'),
            (int) config('koryhub.publication.scan_timeout_seconds'),
        );
    }

    public function scan(string $directory): array
    {
        $report = tempnam(sys_get_temp_dir(), 'kory-gitleaks-');

        try {
            $result = Process::timeout($this->timeoutSeconds)->run([
                $this->binary, 'dir', $directory,
                '--config', resource_path('gitleaks/koryhub.toml'),
                '--report-format', 'json',
                '--report-path', $report,
                '--redact',
                '--no-banner',
                '--exit-code', '0',
            ]);

            if (! $result->successful()) {
                throw new SecretScanFailed(trim($result->errorOutput()) ?: 'gitleaks');
            }

            $findings = json_decode((string) file_get_contents($report), true);
            if (! is_array($findings)) {
                throw new SecretScanFailed('rapport gitleaks illisible');
            }
        } catch (SecretScanFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SecretScanFailed($exception->getMessage(), previous: $exception);
        } finally {
            @unlink($report);
        }

        $prefix = rtrim(str_replace('\\', '/', $directory), '/').'/';

        return array_map(fn (array $finding) => new SecretFinding(
            file: ltrim(str_replace([$prefix, '\\'], ['', '/'], str_replace('\\', '/', (string) ($finding['File'] ?? ''))), '/'),
            line: (int) ($finding['StartLine'] ?? 0),
            rule: (string) ($finding['RuleID'] ?? 'secret'),
        ), $findings);
    }
}
