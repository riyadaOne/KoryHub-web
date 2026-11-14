<?php

namespace App\Console\Commands;

use App\Services\Operations\WeeklyReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Rapport du lundi aux admins (F-ADM-10), chaque lundi à 8 h (routes/console.php). */
#[Signature('koryhub:weekly-report')]
#[Description('Envoie le rapport hebdomadaire aux admins')]
class SendWeeklyReport extends Command
{
    public function handle(WeeklyReport $report): int
    {
        $this->info($report->send().' rapport(s) envoyé(s).');

        return self::SUCCESS;
    }
}
