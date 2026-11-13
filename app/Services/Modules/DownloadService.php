<?php

namespace App\Services\Modules;

use App\Enums\VersionStatus;
use App\Exceptions\ApiError;
use App\Models\ModuleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Téléchargement d'une version par le CLI (W04.4, F-RCH-14, F-RCH-15, F-INT-08, T-25, T-28) :
 * droits, version retirée, limites par heure, compteurs par incrément atomique, lien privé avec
 * le compte, premier téléchargement ; renvoie un lien signé de courte durée vers l'archive.
 */
class DownloadService
{
    /** Statuts téléchargeables : une version dépréciée le reste, avec un avertissement (F-INT-08). */
    private const DOWNLOADABLE = [VersionStatus::Published, VersionStatus::Deprecated];

    /**
     * Compte le téléchargement et renvoie le lien signé. Le module a déjà été trouvé pour ce compte
     * (ModuleFinder : 404 pour le module privé d'un autre).
     */
    public function download(ModuleVersion $version, ?User $user, string $ip): string
    {
        if ($version->status === VersionStatus::Removed) {
            throw ApiError::make(410, 'version_removed', replace: ['version' => $version->version]);
        }
        if (! in_array($version->status, self::DOWNLOADABLE, true) || $version->archive_path === null) {
            throw ApiError::make(404, 'not_found');
        }

        $this->throttle($user, $ip);
        $this->record($version, $user);

        return Storage::disk(config('koryhub.publication.archives_disk'))
            ->temporaryUrl($version->archive_path, now()->addMinutes(config('koryhub.downloads.link_minutes')));
    }

    /** 60 par heure et par adresse IP réelle sans compte, 5 000 par heure et par compte (F-RCH-15). */
    private function throttle(?User $user, string $ip): void
    {
        [$key, $limit, $code] = $user === null
            ? ['downloads:ip:'.$ip, (int) config('koryhub.downloads.guest_per_hour'), 'download_limit_guest']
            : ['downloads:user:'.$user->id, (int) config('koryhub.downloads.user_per_hour'), 'download_limit_user'];

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $seconds = RateLimiter::availableIn($key);

            throw ApiError::make(429, $code, replace: ['limit' => $limit])
                ->withExtra(['retry_at' => now()->addSeconds($seconds)->utc()->toIso8601ZuluString()])
                ->withHeaders(['Retry-After' => (string) $seconds]);
        }

        RateLimiter::hit($key, 3600);
    }

    /** Compteurs, statistiques du jour, lien privé, premier téléchargement et téléchargement par un tiers. */
    private function record(ModuleVersion $version, ?User $user): void
    {
        $module = $version->module;
        $now = now();

        DB::transaction(function () use ($version, $module, $user, $now) {
            $version->newQuery()->whereKey($version->id)->increment('download_count');
            $module->newQuery()->whereKey($module->id)->increment('download_count');

            $version->newQuery()->whereKey($version->id)->whereNull('first_downloaded_at')
                ->update(['first_downloaded_at' => $now]);

            if ($user === null || $user->id !== $module->author_id) {
                $module->newQuery()->whereKey($module->id)->whereNull('third_party_downloaded_at')
                    ->update(['third_party_downloaded_at' => $now]);
            }

            DB::statement(
                'INSERT INTO download_stats (id, module_id, version_id, day, count) VALUES (?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE count = count + 1',
                [(string) Str::uuid7(), $module->id, $version->id, $now->toDateString()],
            );

            if ($user !== null) {
                DB::statement(
                    'INSERT INTO module_downloads (id, user_id, module_id, version_id, first_at, last_at, count) VALUES (?, ?, ?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE last_at = VALUES(last_at), count = count + 1',
                    [(string) Str::uuid7(), $user->id, $module->id, $version->id, $now, $now],
                );
            }
        });
    }
}
