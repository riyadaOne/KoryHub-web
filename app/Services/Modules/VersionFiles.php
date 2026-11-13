<?php

namespace App\Services\Modules;

use App\Models\ModuleVersion;
use App\Models\VersionFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contenu des fichiers d'une version publiée (S-06, images du README) : lu dans l'archive gardée,
 * avec les mêmes contrôles qu'à la publication, puis mis en cache sans limite de durée (une
 * version publiée n'est jamais modifiée, F-REG-10).
 */
class VersionFiles
{
    /** Taille au-delà de laquelle un fichier n'est pas affiché dans le navigateur. */
    public const MAX_DISPLAY_BYTES = 256 * 1024;

    /** Octets exacts du fichier, ou null (archive absente, fichier trop gros pour l'affichage). */
    public function content(ModuleVersion $version, VersionFile $file): ?string
    {
        if ($version->archive_path === null || $file->size > self::MAX_DISPLAY_BYTES) {
            return null;
        }

        $cached = Cache::rememberForever("version-file:{$version->id}:{$file->path_hash}", function () use ($version, $file) {
            $content = $this->extractOne($version, $file->path);

            return $content === null ? null : base64_encode($content);
        });

        return $cached === null ? null : base64_decode($cached);
    }

    private function extractOne(ModuleVersion $version, string $path): ?string
    {
        $disk = Storage::disk(config('koryhub.publication.archives_disk'));
        if (! $disk->exists($version->archive_path)) {
            return null;
        }

        $workdir = storage_path('app/private/reading/'.Str::uuid7());
        File::ensureDirectoryExists($workdir, 0700);
        $archive = $workdir.'.tar.gz';

        try {
            file_put_contents($archive, $disk->get($version->archive_path));
            ArchiveReader::fromConfig()->extract($archive, $workdir);
            $target = $workdir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

            return is_file($target) ? (string) file_get_contents($target) : null;
        } catch (PublicationError) {
            return null;
        } finally {
            File::deleteDirectory($workdir);
            @unlink($archive);
        }
    }
}
