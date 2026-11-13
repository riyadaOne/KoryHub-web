<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\Search\ScoreCalculator;
use App\Services\Search\SearchText;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Score de classement (TRD §14) : recalculé chaque heure pour les modules publics
 * (routes/console.php). Avec --reindex, le texte cherchable de chaque module est aussi refait.
 */
#[Signature('koryhub:scores {--reindex : Refaire aussi le texte cherchable}')]
#[Description('Recalcule le score de classement des modules publics')]
class RefreshScores extends Command
{
    public function handle(ScoreCalculator $scores, SearchText $search): int
    {
        if ($this->option('reindex')) {
            Module::query()->chunkById(200, fn ($modules) => $modules->each(fn (Module $m) => $search->refresh($m)));
        }

        $this->info($scores->refreshAll().' module(s) classé(s).');

        return self::SUCCESS;
    }
}
