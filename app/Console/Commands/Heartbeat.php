<?php

namespace App\Console\Commands;

use App\Services\Operations\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Signal de vie du planificateur à Healthchecks.io (F-ADM-09), toutes les 5 minutes. */
#[Signature('koryhub:heartbeat')]
#[Description('Signal de vie du planificateur (Healthchecks.io)')]
class Heartbeat extends Command
{
    public function handle(BackupService $backups): int
    {
        $backups->heartbeat();

        return self::SUCCESS;
    }
}
