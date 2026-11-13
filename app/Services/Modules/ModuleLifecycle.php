<?php

namespace App\Services\Modules;

use App\Enums\ModuleStatus;
use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Exceptions\InvalidTransition;
use App\Models\Category;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\Tag;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\SemVer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cycle de vie des modules et de leurs versions (BACKEND-SCHEMA §6, §8, F-REG-13 à F-REG-18) :
 * dernière version stable, dépréciation, suppression d'une version sous 72 h, réglages historisés,
 * archivage, suppression d'un module privé. Chaque changement est noté dans l'audit.
 */
class ModuleLifecycle
{
    /** Délai pendant lequel une version publiée peut être supprimée (F-REG-17). */
    public const DELETION_WINDOW_HOURS = 72;

    public function __construct(private AuditLogger $audit) {}

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

    /** Déprécie une version publiée, avec un message montré aux utilisateurs (F-REG-15). */
    public function deprecate(ModuleVersion $version, string $message, User $actor): void
    {
        $this->transition($version, VersionStatus::Deprecated, [
            'deprecated_at' => now(),
            'deprecation_message' => mb_substr(trim($message), 0, 255),
        ], $actor);
    }

    /** Annule la dépréciation : la version redevient publiée. */
    public function undeprecate(ModuleVersion $version, User $actor): void
    {
        $this->transition($version, VersionStatus::Published, ['deprecated_at' => null, 'deprecation_message' => null], $actor);
    }

    /**
     * Une version publiée se supprime dans les 72 h (F-REG-17) ; la condition « jamais
     * téléchargée » s'ajoute en W04. Son numéro reste pris (la ligne est supprimée logiquement).
     */
    public function canDeleteVersion(ModuleVersion $version): bool
    {
        return in_array($version->status, [VersionStatus::Published, VersionStatus::Deprecated], true)
            && $version->published_at !== null
            && $version->published_at->gt(now()->subHours(self::DELETION_WINDOW_HOURS));
    }

    public function deleteVersion(ModuleVersion $version, User $actor): void
    {
        if (! $this->canDeleteVersion($version)) {
            throw new ModuleRuleViolation('modules.errors.version_not_deletable');
        }

        DB::transaction(function () use ($version, $actor) {
            $version->delete();
            $this->audit->record('version.deleted', $version, ['deleted_at' => [null, now()]], actor: $actor);
            $this->refreshLatestVersion($version->module);
        });

        $this->deleteArchive($version);
    }

    /**
     * Réglages de la fiche (F-REG-13, F-REG-14, F-REG-18) : description, catégorie, étiquettes,
     * visibilité ; chaque champ changé est noté dans l'audit.
     *
     * @param  array{description: ?string, category_id: ?string, tags: list<string>, visibility: Visibility}  $settings
     */
    public function updateSettings(Module $module, array $settings, User $actor): void
    {
        if ($module->visibility === Visibility::Public && $settings['visibility'] === Visibility::Private && ! $this->canMakePrivate($module)) {
            throw new ModuleRuleViolation('modules.errors.cannot_make_private');
        }

        DB::transaction(function () use ($module, $settings, $actor) {
            $before = [
                'description' => $module->description,
                'category' => $module->category?->slug,
                'visibility' => $module->visibility,
                'tags' => $module->tags->pluck('name')->sort()->implode(', '),
            ];

            $module->forceFill([
                'description' => $settings['description'],
                'category_id' => $settings['category_id'],
                'visibility' => $settings['visibility'],
            ])->save();

            $ids = collect($settings['tags'])->unique()->map(fn (string $name) => Tag::withTrashed()->firstOrCreate(['name' => $name])->id);
            $module->tags()->sync($ids->all());
            $module->load('tags', 'category');

            $after = [
                'description' => $module->description,
                'category' => $module->category_id ? Category::find($module->category_id)?->slug : null,
                'visibility' => $module->visibility,
                'tags' => $module->tags->pluck('name')->sort()->implode(', '),
            ];

            $changes = [];
            foreach ($before as $field => $value) {
                if ($value !== $after[$field]) {
                    $changes[$field] = [$value, $after[$field]];
                }
            }

            if ($changes !== []) {
                $this->audit->record('module.updated', $module, $changes, actor: $actor);
            }
        });
    }

    /** Passage en privé interdit une fois le module téléchargé par un autre compte (W04, BACKEND-SCHEMA §8). */
    public function canMakePrivate(Module $module): bool
    {
        return true;
    }

    /** Archive un module : plus de nouvelle version, toujours visible et téléchargeable (F-REG-16). */
    public function archive(Module $module, User $actor): void
    {
        $this->moduleTransition($module, ModuleStatus::Archived, ['archived_at' => now()], $actor);
    }

    public function unarchive(Module $module, User $actor): void
    {
        $this->moduleTransition($module, ModuleStatus::Active, ['archived_at' => null], $actor);
    }

    /**
     * Supprime définitivement un module privé (F-REG-17) : versions et archives effacées, nom
     * jamais réutilisé (lignes supprimées logiquement, unicité qui les compte). Un module public
     * ne se supprime pas.
     */
    public function deleteModule(Module $module, User $actor): void
    {
        if ($module->visibility !== Visibility::Private) {
            throw new ModuleRuleViolation('modules.errors.public_not_deletable');
        }

        $versions = $module->versions()->get();

        DB::transaction(function () use ($module, $versions, $actor) {
            $module->forceFill(['latest_version_id' => null])->save();
            $versions->each->delete();
            $module->delete();
            $this->audit->record('module.deleted', $module, ['deleted_at' => [null, now()]], actor: $actor);
        });

        $versions->each(fn (ModuleVersion $version) => $this->deleteArchive($version));
    }

    /** @param  array<string, mixed>  $attributes */
    private function transition(ModuleVersion $version, VersionStatus $next, array $attributes, User $actor): void
    {
        if (! in_array($next, $version->status->allowedNext(), true)) {
            throw new InvalidTransition($version->status, $next);
        }

        DB::transaction(function () use ($version, $next, $attributes, $actor) {
            $previous = $version->status;
            $version->forceFill(['status' => $next, ...$attributes])->save();
            $this->audit->record('version.status_changed', $version, ['status' => [$previous, $next]], actor: $actor);
            $this->refreshLatestVersion($version->module);
        });
    }

    /** @param  array<string, mixed>  $attributes */
    private function moduleTransition(Module $module, ModuleStatus $next, array $attributes, User $actor): void
    {
        if ($module->status === $next) {
            throw new InvalidTransition($module->status, $next);
        }

        $previous = $module->status;
        $module->forceFill(['status' => $next, ...$attributes])->save();
        $this->audit->record('module.status_changed', $module, ['status' => [$previous, $next]], actor: $actor);
    }

    private function deleteArchive(ModuleVersion $version): void
    {
        if ($version->archive_path !== null) {
            Storage::disk(config('koryhub.publication.archives_disk'))->delete($version->archive_path);
        }
    }
}
