<?php

namespace App\Services\Modules;

use App\Enums\VersionStatus;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trouve un module pour les pages publiques (APPFLOW §6.1) : 404 pour un module privé d'un autre
 * compte (comme s'il n'existait pas), et pour un module sans version publiée vu par un autre que
 * son auteur. L'auteur voit aussi ses versions rejetées.
 */
class ModuleFinder
{
    public function find(string $author, string $name, ?User $viewer): Module
    {
        $module = Module::query()
            ->whereHas('author', fn (Builder $q) => $q->where('handle', $author))
            ->where('name', $name)
            ->with(['author', 'category.translations', 'tags', 'latestVersion'])
            ->first();

        abort_if($module === null || ! $module->isVisibleTo($viewer), 404);

        if (! $this->isAuthor($module, $viewer)) {
            abort_unless($module->versions()->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated])->exists(), 404);
        }

        return $module;
    }

    /** Versions visibles : publiées et dépréciées pour tous, plus les rejetées pour l'auteur ; récentes d'abord. */
    public function versions(Module $module, ?User $viewer)
    {
        return $module->versions()
            ->when(! $this->isAuthor($module, $viewer), fn (Builder $q) => $q->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated]))
            ->orderByDesc('major')->orderByDesc('minor')->orderByDesc('patch')->orderByDesc('submitted_at')
            ->get();
    }

    /** Une version par son numéro ; une version rejetée n'est visible que par l'auteur. */
    public function version(Module $module, string $number, ?User $viewer): ModuleVersion
    {
        $version = $module->versions()
            ->where('version', $number)
            ->orderByRaw("status = 'rejected'")
            ->latest('submitted_at')
            ->first();

        abort_if($version === null, 404);
        abort_if($version->status === VersionStatus::Rejected && ! $this->isAuthor($module, $viewer), 404);

        return $version;
    }

    /** Version affichée par défaut : la dernière stable, sinon la plus récente publiée. */
    public function current(Module $module): ?ModuleVersion
    {
        return $module->latestVersion ?? $module->versions()
            ->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated])
            ->latest('published_at')
            ->first();
    }

    public function isAuthor(Module $module, ?User $viewer): bool
    {
        return $viewer !== null && $viewer->is($module->author);
    }
}
