<?php

namespace App\Console\Commands;

use App\Services\Cli\DeviceAuthorizationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Codes de connexion du CLI (TRD §7.3) : codes échus passés à « expiré », lignes effacées
 * 7 jours après leur expiration. Planifiée toutes les 15 minutes (routes/console.php).
 */
#[Signature('koryhub:device-codes-prune')]
#[Description('Expire et efface les anciens codes de connexion du CLI')]
class PruneDeviceCodes extends Command
{
    public function handle(DeviceAuthorizationService $devices): int
    {
        $this->info($devices->prune().' code(s) effacé(s).');

        return self::SUCCESS;
    }
}
