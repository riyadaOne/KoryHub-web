<?php

namespace App\Services\Modules;

use App\Enums\NotificationType;
use App\Enums\Visibility;
use App\Models\ModuleDownload;
use App\Models\ModuleVersion;
use App\Models\User;
use App\Notifications\ModuleNotification;

/**
 * Alertes aux comptes qui ont téléchargé un module (F-INT-06, APPFLOW §5.3) : nouvelle version
 * stable publiée, version dépréciée. Jamais à l'auteur ; jamais pour une pré-version ni pour un
 * module privé. Réglables sur S-43 ; la mise à jour n'est jamais forcée.
 */
class DownloaderNotifier
{
    /** Nouvelle version stable : chaque compte qui a téléchargé une version plus ancienne. */
    public function versionPublished(ModuleVersion $version): void
    {
        $module = $version->module;
        if ($version->isPrerelease() || $module->visibility !== Visibility::Public) {
            return;
        }

        $installed = ModuleDownload::query()
            ->where('module_id', $module->id)
            ->where('version_id', '!=', $version->id)
            ->where('user_id', '!=', $module->author_id)
            ->with('version')
            ->orderBy('last_at')
            ->get()
            ->keyBy('user_id'); // dernière version téléchargée par compte

        User::query()->whereIn('id', $installed->keys())->each(function (User $user) use ($installed, $module, $version) {
            $user->notify(new ModuleNotification(NotificationType::UpdateAvailable, [
                'module' => $module->fullName(),
                'version' => $version->version,
                'installed' => $installed[$user->id]->version->version,
            ]));
        });
    }

    /** Version dépréciée : chaque compte qui l'a téléchargée, avec le message de l'auteur. */
    public function versionDeprecated(ModuleVersion $version): void
    {
        $module = $version->module;
        $userIds = ModuleDownload::query()
            ->where('version_id', $version->id)
            ->where('user_id', '!=', $module->author_id)
            ->pluck('user_id');

        User::query()->whereIn('id', $userIds)->each(function (User $user) use ($module, $version) {
            $user->notify(new ModuleNotification(NotificationType::VersionDeprecated, [
                'module' => $module->fullName(),
                'version' => $version->version,
                'reason' => $version->deprecation_message,
            ]));
        });
    }
}
