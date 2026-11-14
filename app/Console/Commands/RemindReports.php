<?php

namespace App\Console\Commands;

use App\Services\Moderation\ModerationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Relance des signalements (F-MOD-12) : chaque jour, les admins sont relancés pour chaque
 * signalement ouvert depuis plus de 48 h, une fois par jour. Planifiée (routes/console.php).
 */
#[Signature('koryhub:reports-remind')]
#[Description('Relance les admins pour les signalements ouverts depuis plus de 48 h')]
class RemindReports extends Command
{
    public function handle(ModerationService $moderation): int
    {
        $this->info($moderation->remindStale().' relance(s) envoyée(s).');

        return self::SUCCESS;
    }
}
