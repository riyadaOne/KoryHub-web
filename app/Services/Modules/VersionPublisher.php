<?php

namespace App\Services\Modules;

use App\Enums\License;
use App\Enums\ModuleStatus;
use App\Enums\NotificationType;
use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Modules\Secrets\SecretScanFailed;
use App\Services\Modules\Secrets\SecretScanner;
use App\Support\SemVer;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Publie une version envoyée par kory push (F-REG-03 à F-REG-12, TRD §13, §22) : droits du
 * compte, empreinte recalculée, archive lue sous limites, manifeste, numéro de version, fichiers
 * binaires et SVG, secrets ; puis version publiée (archive stockée, empreinte de chaque fichier)
 * ou rejetée (code et motif), avec audit et notification de l'auteur.
 */
class VersionPublisher
{
    /** Règle du nom d'un module : comme un nom @auteur, 2 à 64 caractères. */
    public const NAME_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])$/';

    public function __construct(
        private SecretScanner $scanner,
        private AuditLogger $audit,
        private ModuleLifecycle $lifecycle,
    ) {}

    /**
     * @param  array{visibility?: string|null, rights_certified?: bool, notice_accepted?: bool}  $options
     *
     * @throws PublicationError
     */
    public function publish(User $user, string $author, string $name, UploadedFile $archive, string $sha256, array $options = []): ModuleVersion
    {
        if ($author !== $user->handle) {
            throw new PublicationError('author_mismatch', ['handle' => '@'.$user->handle], 403);
        }
        if (! $user->hasVerifiedEmail()) {
            throw new PublicationError('email_unverified', status: 403);
        }
        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw new PublicationError('module_name_invalid');
        }
        if ($archive->getSize() > config('koryhub.publication.max_archive_kb') * 1024) {
            throw new PublicationError('archive_too_large', ['max_mb' => intdiv((int) config('koryhub.publication.max_archive_kb'), 1024)], 413);
        }

        $actualSha = hash_file('sha256', $archive->getRealPath());
        if (! hash_equals($actualSha, strtolower($sha256))) {
            throw new PublicationError('checksum_mismatch');
        }

        $acceptsTerms = ($options['rights_certified'] ?? false) && ($options['notice_accepted'] ?? false);
        if (! $user->hasAcceptedPublicationTerms() && ! $acceptsTerms) {
            throw new PublicationError('terms_required');
        }

        $module = Module::withTrashed()->where('author_id', $user->id)->where('name', $name)->first();
        if ($module?->trashed()) {
            throw new PublicationError('module_name_taken', status: 409);
        }
        if ($module?->status === ModuleStatus::Archived) {
            throw new PublicationError('module_archived', status: 409);
        }

        $workdir = storage_path('app/private/publishing/'.Str::uuid7());
        File::ensureDirectoryExists($workdir, 0700);

        try {
            $entries = ArchiveReader::fromConfig()->extract($archive->getRealPath(), $workdir);
            $manifest = $this->readManifest($entries, $workdir);
            $this->checkVersionNumber($module, $manifest->version, $user->handle.'/'.$name);

            $context = [$user, $name, $module, $manifest, $actualSha, $archive->getSize(), $options];

            try {
                $license = $this->checkContent($manifest, $entries, $workdir, '@'.$user->handle.'/'.$name);
            } catch (PublicationError $error) {
                if ($error->keepsRejectedVersion()) {
                    $this->reject($error, ...$context);
                }
                throw $error;
            }

            return $this->accept($license, $entries, $workdir, $archive, ...$context);
        } finally {
            File::deleteDirectory($workdir);
        }
    }

    /** @param  array<string, array<string, mixed>>  $entries */
    private function readManifest(array $entries, string $workdir): Manifest
    {
        if (! isset($entries['kory.json'])) {
            throw new PublicationError('manifest_missing');
        }
        if (! isset($entries['README.md'])) {
            throw new PublicationError('readme_missing');
        }
        if ($entries['kory.json']['size'] > 1024 * 1024) {
            throw new PublicationError('manifest_missing');
        }

        return Manifest::read((string) file_get_contents($workdir.'/kory.json'));
    }

    /** Numéro libre et plus haut que toute version non rejetée, y compris supprimée (BACKEND-SCHEMA §8). */
    private function checkVersionNumber(?Module $module, SemVer $version, string $fullName): void
    {
        if ($module === null) {
            return;
        }

        $taken = $module->versions()->withTrashed()->where('status', '!=', VersionStatus::Rejected)->pluck('version');

        if ($taken->contains((string) $version)) {
            throw new PublicationError('version_exists', ['version' => (string) $version, 'module' => '@'.$fullName], 409);
        }

        $highest = $taken->map(fn (string $v) => SemVer::parse($v))->filter()
            ->reduce(fn (?SemVer $carry, SemVer $v) => $carry === null || $v->greaterThan($carry) ? $v : $carry);

        if ($highest !== null && ! $version->greaterThan($highest)) {
            throw new PublicationError('version_not_higher', ['version' => (string) $version, 'highest' => (string) $highest], 409);
        }
    }

    /**
     * Contrôles qui laissent une version rejetée : manifeste, licence, fichiers, binaires, SVG, secrets.
     *
     * @param  array<string, array{size: int, sha256: string, is_image: bool, binary: bool, svg: bool}>  $entries
     */
    private function checkContent(Manifest $manifest, array $entries, string $workdir, string $fullName): License
    {
        $files = array_values(array_diff(array_keys($entries), ['kory.json', 'README.md']));
        $license = $manifest->validate($fullName, $files);
        $manifest->requirements();
        $manifest->architecture($files);

        foreach ($entries as $path => $entry) {
            if ($entry['svg']) {
                throw new PublicationError('svg_forbidden', ['path' => $path]);
            }
            if ($entry['binary']) {
                throw new PublicationError('binary_forbidden', ['path' => $path]);
            }
        }

        try {
            $findings = $this->scanner->scan($workdir);
        } catch (SecretScanFailed $exception) {
            report($exception);
            throw new PublicationError('scan_failed', status: 503);
        }

        if ($findings !== []) {
            $first = $findings[0];
            throw new PublicationError('secret_detected', ['file' => $first->file, 'line' => $first->line, 'rule' => $first->rule]);
        }

        return $license;
    }

    /**
     * @param  array<string, array{size: int, sha256: string, is_image: bool}>  $entries
     * @param  array<string, mixed>  $options
     */
    private function accept(License $license, array $entries, string $workdir, UploadedFile $archive, User $user, string $name, ?Module $module, Manifest $manifest, string $sha256, int $size, array $options): ModuleVersion
    {
        $disk = Storage::disk(config('koryhub.publication.archives_disk'));
        $stored = null;

        try {
            $version = DB::transaction(function () use ($license, $entries, $workdir, $archive, $user, $name, $module, $manifest, $sha256, $size, $options, $disk, &$stored) {
                $module ??= $this->createModule($user, $name, $manifest, $options);

                $version = $this->newVersion($module, $user, $manifest, $sha256, $size, $license, count($entries) - 2);
                $version->forceFill([
                    'status' => VersionStatus::Published,
                    'published_at' => now(),
                    'readme' => (string) file_get_contents($workdir.'/README.md'),
                ])->save();

                $fileIds = [];
                foreach ($entries as $path => $entry) {
                    $fileIds[$path] = $version->files()->create([
                        'path' => $path,
                        'path_hash' => hash('sha256', $path),
                        'size' => $entry['size'],
                        'sha256' => $entry['sha256'],
                        'is_image' => $entry['is_image'],
                    ])->id;
                }

                $this->storeArchitecture($version, $manifest, $fileIds);

                $stored = "modules/{$module->id}/{$version->id}.tar.gz";
                $disk->putFileAs(dirname($stored), $archive, basename($stored));
                $version->forceFill(['archive_path' => $stored])->save();

                $this->syncTags($module, $manifest->tags);
                $module->forceFill(['language' => $manifest->language ?? $module->language])->save();
                $this->lifecycle->refreshLatestVersion($module);

                if (! $user->hasAcceptedPublicationTerms()) {
                    $user->forceFill(['rights_certified_at' => now(), 'publication_notice_accepted_at' => now()])->save();
                }

                $this->audit->record('version.published', $version, [
                    'version' => [null, $version->version],
                    'archive_sha256' => [null, $sha256],
                ], actor: $user);

                return $version;
            });
        } catch (QueryException $exception) {
            if ($stored !== null) {
                $disk->delete($stored);
            }
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new PublicationError('version_exists', ['version' => (string) $manifest->version, 'module' => '@'.$user->handle.'/'.$name], 409);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($stored !== null) {
                $disk->delete($stored);
            }
            throw $exception;
        }

        $user->notify(new ModuleNotification(NotificationType::VersionPublished, [
            'module' => $version->module->fullName(),
            'version' => $version->version,
        ]));

        return $version;
    }

    /** @param  array<string, mixed>  $options */
    private function reject(PublicationError $error, User $user, string $name, ?Module $module, Manifest $manifest, string $sha256, int $size, array $options): void
    {
        $version = DB::transaction(function () use ($error, $user, $name, $module, $manifest, $sha256, $size, $options) {
            $module ??= $this->createModule($user, $name, $manifest, $options);

            $version = $this->newVersion($module, $user, $manifest, $sha256, $size, null, 0);
            $version->forceFill([
                'status' => VersionStatus::Rejected,
                'rejection_code' => $error->errorCode,
                'rejection_reason' => $error->message(),
            ])->save();

            $this->audit->record('version.rejected', $version, ['status' => [VersionStatus::Submitted, VersionStatus::Rejected]], $error->errorCode, $user);

            return $version;
        });

        $user->notify(new ModuleNotification(NotificationType::VersionRejected, [
            'module' => $version->module->fullName(),
            'version' => $version->version,
            'reason' => $version->rejection_reason,
        ]));
    }

    /** @param  array<string, mixed>  $options */
    private function createModule(User $user, string $name, Manifest $manifest, array $options): Module
    {
        $module = $user->modules()->create([
            'name' => $name,
            'visibility' => Visibility::tryFrom((string) ($options['visibility'] ?? '')) ?? Visibility::Private,
            'description' => $manifest->description === null ? null : mb_substr($manifest->description, 0, 500),
            'language' => $manifest->language,
        ]);

        $this->audit->record('module.created', $module, ['visibility' => [null, $module->visibility]], actor: $user);

        return $module;
    }

    private function newVersion(Module $module, User $user, Manifest $manifest, string $sha256, int $size, ?License $license, int $filesCount): ModuleVersion
    {
        return $module->versions()->create([
            'version' => (string) $manifest->version,
            'major' => $manifest->version->major,
            'minor' => $manifest->version->minor,
            'patch' => $manifest->version->patch,
            'prerelease' => $manifest->version->prerelease,
            'status' => VersionStatus::Submitted,
            'description' => $manifest->description === null ? null : mb_substr($manifest->description, 0, 500),
            'license' => $license ?? License::fromSpdx($manifest->licenseSpdx ?? '') ?? License::Mit,
            'language' => $manifest->language,
            'archive_sha256' => $sha256,
            'archive_size' => $size,
            'files_count' => max(0, $filesCount),
            'published_by' => $user->id,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Copie l'architecture et les prérequis du manifeste (déjà validés) dans leurs tables.
     *
     * @param  array<string, string>  $fileIds  chemin => id de version_files
     */
    private function storeArchitecture(ModuleVersion $version, Manifest $manifest, array $fileIds): void
    {
        foreach ($manifest->requirements() as $requirement) {
            $version->requirements()->create([
                'ecosystem' => $requirement['ecosystem'],
                'name' => $requirement['name'],
                'version_constraint' => $requirement['version'],
            ]);
        }

        $architecture = $manifest->architecture(array_keys($fileIds));
        $componentIds = [];
        foreach ($architecture['components'] as $position => $component) {
            $componentIds[$component['id']] = $version->components()->create([
                'key' => $component['id'],
                'kind' => $component['kind'],
                'file_id' => $fileIds[$component['file']],
                'position' => $position,
            ])->id;
        }
        foreach ($architecture['links'] as $link) {
            $version->links()->create([
                'from_component_id' => $componentIds[$link['from']],
                'to_component_id' => $componentIds[$link['to']],
                'kind' => $link['kind'],
            ]);
        }
    }

    /** @param  list<string>  $names */
    private function syncTags(Module $module, array $names): void
    {
        if ($names === []) {
            return;
        }

        $ids = collect($names)->unique()->map(fn (string $tag) => Tag::withTrashed()->firstOrCreate(['name' => $tag])->id);
        $module->tags()->sync($ids->all());
    }
}
