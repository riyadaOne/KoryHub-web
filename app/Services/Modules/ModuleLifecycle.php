<?php

namespace App\Services\Modules;

use App\Enums\VersionStatus;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Support\SemVer;

/**
 * Cycle de vie des modules et de leurs versions (BACKEND-SCHEMA §6, §8). Bloc A : la dernière
 * version stable ; dépréciation, archivage et suppressions arrivent au bloc B.
 */
class ModuleLifecycle
{
    /**
     * latest_version_id = plus haute version stable (sans pré-version), publiée ou dépréciée
     * (BACKEND-SCHEMA §8) ; null s'il n'y en a pas.
     */
    public function refreshLatestVersion(Module $module): void
    {
        $latest = $module->versions()
            ->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated])
            ->whereNull('prerelease')
            ->get()
            ->reduce(function (?ModuleVersion $carry, ModuleVersion $version) {
                if ($carry === null) {
                    return $version;
                }

                return SemVer::parse($version->version)->greaterThan(SemVer::parse($carry->version)) ? $version : $carry;
            });

        $module->forceFill(['latest_version_id' => $latest?->id])->save();
    }
}
