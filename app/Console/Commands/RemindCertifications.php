<?php

namespace App\Console\Commands;

use App\Services\Certification\CertificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Relance des dossiers de certification (F-MOD-07) : chaque jour, le développeur dont le
 * complément attend depuis 7 jours est relancé, une fois par jour. Planifiée (routes/console.php).
 */
#[Signature('koryhub:certifications-remind')]
#[Description('Relance les développeurs qui doivent un complément de certification')]
class RemindCertifications extends Command
{
    public function handle(CertificationService $certifications): int
    {
        $this->info($certifications->remindStale().' relance(s) envoyée(s).');

        return self::SUCCESS;
    }
}
