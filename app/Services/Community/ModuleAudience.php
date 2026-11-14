<?php

namespace App\Services\Community;

use App\Enums\NotificationType;
use App\Enums\Visibility;
use App\Enums\WatchLevel;
use App\Models\Module;
use App\Models\ModuleDownload;
use App\Models\ModuleVersion;
use App\Models\User;
use App\Models\Watch;
use App\Notifications\ModuleNotification;
use Illuminate\Support\Collection;

/**
 * Audience d'un événement de module (W05.7, F-REG-19, F-INT-06, F-RCH-09) : abonnés et comptes
 * qui ont téléchargé le module en étant connectés. Jamais l'auteur, jamais un module privé,
 * jamais un compte qui a choisi « Ignorer » ; une seule notification par compte et par événement.
 * Réutilisé par la certification (W06) et la modération (W07).
 */
class ModuleAudience
{
    /** Nouvelle version stable : abonnés (toutes les activités, versions), puis utilisateurs d'une version plus ancienne. */
    public function versionPublished(ModuleVersion $version): void
    {
        $module = $version->module;
        if ($version->isPrerelease() || $module->visibility !== Visibility::Public) {
            return;
        }

        $ignored = $this->watchers($module, [WatchLevel::Ignore]);
        $watchers = $this->watchers($module, [WatchLevel::All, WatchLevel::Releases]);

        $this->notify($watchers, fn () => new ModuleNotification(NotificationType::NewVersionWatched, [
            'module' => $module->fullName(),
            'version' => $version->version,
        ]));

        $installed = ModuleDownload::query()
            ->where('module_id', $module->id)
            ->where('version_id', '!=', $version->id)
            ->whereNotIn('user_id', $ignored->merge($watchers)->push($module->author_id))
            ->with('version')
            ->orderBy('last_at')
            ->get()
            ->keyBy('user_id'); // dernière version téléchargée par compte

        $this->notify($installed->keys(), fn (User $user) => new ModuleNotification(NotificationType::UpdateAvailable, [
            'module' => $module->fullName(),
            'version' => $version->version,
            'installed' => $installed[$user->id]->version->version,
        ]));
    }

    /** Version dépréciée : abonnés « toutes les activités » et comptes qui l'ont téléchargée. */
    public function versionDeprecated(ModuleVersion $version): void
    {
        $module = $version->module;
        if ($module->visibility !== Visibility::Public) {
            return;
        }

        $recipients = $this->watchers($module, [WatchLevel::All])
            ->merge(ModuleDownload::query()->where('version_id', $version->id)->where('user_id', '!=', $module->author_id)->pluck('user_id'))
            ->diff($this->watchers($module, [WatchLevel::Ignore]));

        $this->notify($recipients, fn () => new ModuleNotification(NotificationType::VersionDeprecated, [
            'module' => $module->fullName(),
            'version' => $version->version,
            'reason' => $version->deprecation_message,
        ]));
    }

    /**
     * Certification accordée ou révoquée (F-MOD-14, W06.3) : abonnés (toutes les activités ou
     * versions) et comptes qui ont téléchargé la version ; l'auteur est prévenu à part.
     */
    public function certificationChanged(ModuleVersion $version, NotificationType $type, array $params): void
    {
        $module = $version->module;
        if ($module->visibility !== Visibility::Public) {
            return;
        }

        $recipients = $this->watchers($module, [WatchLevel::All, WatchLevel::Releases])
            ->merge(ModuleDownload::query()->where('version_id', $version->id)->where('user_id', '!=', $module->author_id)->pluck('user_id'))
            ->diff($this->watchers($module, [WatchLevel::Ignore]));

        $this->notify($recipients, fn () => new ModuleNotification($type, $params));
    }

    /**
     * Version signalée à risque ou retirée (F-MOD-14, W07) : même audience que la certification ;
     * l'auteur est prévenu à part.
     */
    public function versionModerated(ModuleVersion $version, NotificationType $type, array $params): void
    {
        $this->certificationChanged($version, $type, $params);
    }

    /** Module archivé : abonnés « toutes les activités ». */
    public function moduleArchived(Module $module): void
    {
        if ($module->visibility !== Visibility::Public) {
            return;
        }

        $this->notify($this->watchers($module, [WatchLevel::All]), fn () => new ModuleNotification(NotificationType::ModuleArchived, [
            'module' => $module->fullName(),
        ]));
    }

    /**
     * @param  list<WatchLevel>  $levels
     * @return Collection<int, string>
     */
    private function watchers(Module $module, array $levels): Collection
    {
        return Watch::query()->where('module_id', $module->id)->where('user_id', '!=', $module->author_id)->whereIn('level', $levels)->pluck('user_id');
    }

    /** @param  Collection<int, string>  $userIds */
    private function notify(Collection $userIds, callable $notification): void
    {
        User::query()
            ->whereIn('id', $userIds->unique()->values())
            ->each(function (User $user) use ($notification) {
                $user->notify($notification($user));
            });
    }
}
