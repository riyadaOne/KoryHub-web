<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VersionStatus;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Module vu par le CLI (kory list, push, pull, update) : ouvert sans compte ; un module privé
 * d'un autre compte répond 404 (W04.3, T-31).
 */
class ModuleController extends Controller
{
    /** Statuts montrés au CLI : une version retirée reste listée pour expliquer le refus. */
    private const LISTED = [VersionStatus::Published, VersionStatus::Deprecated, VersionStatus::Removed];

    /**
     * Un module et ses versions.
     *
     * Versions publiées, dépréciées et retirées, les plus récentes d'abord.
     */
    public function show(Request $request, string $author, string $module): JsonResponse
    {
        $found = $this->find($request, $author, $module);

        $versions = $found->versions()
            ->whereIn('status', self::LISTED)
            ->orderByDesc('major')->orderByDesc('minor')->orderByDesc('patch')->orderByDesc('published_at')
            ->get();

        return response()->json([
            /** Nom complet @auteur/nom. */
            'name' => $found->fullName(),
            /** public ou private. */
            'visibility' => $found->visibility->value,
            /** active ou archived. */
            'status' => $found->status->value,
            /** Dernière version stable publiée, ou null. */
            'latest_version' => $found->latestVersion?->version,
            'description' => $found->description,
            'language' => $found->language,
            /** Certification (W06) : toujours false pour l'instant. */
            'certified' => false,
            /** Téléchargements, toutes versions. */
            'download_count' => $found->download_count,
            'versions' => $versions->map(fn (ModuleVersion $v) => [
                'version' => $v->version,
                /** published, deprecated ou removed. */
                'status' => $v->status->value,
                'prerelease' => $v->isPrerelease(),
                'published_at' => $v->published_at?->toIso8601ZuluString(),
                /** Empreinte SHA-256 de l'archive. */
                'sha256' => $v->archive_sha256,
                'size' => $v->archive_size,
                'deprecation_message' => $v->deprecation_message,
            ])->values()->all(),
        ]);
    }

    /**
     * Une version : prérequis et fichiers (chemin, taille, empreinte).
     */
    public function version(Request $request, string $author, string $module, string $version): JsonResponse
    {
        $found = $this->find($request, $author, $module);

        /** @var ModuleVersion|null $v */
        $v = $found->versions()->where('version', $version)->whereIn('status', self::LISTED)
            ->with(['requirements', 'files'])->first();
        abort_if($v === null, 404);

        return response()->json([
            'name' => $found->fullName(),
            'version' => $v->version,
            /** published, deprecated ou removed. */
            'status' => $v->status->value,
            'prerelease' => $v->isPrerelease(),
            'license' => $v->license->value,
            'language' => $v->language,
            'published_at' => $v->published_at?->toIso8601ZuluString(),
            'sha256' => $v->archive_sha256,
            'size' => $v->archive_size,
            'deprecation_message' => $v->deprecation_message,
            'requires' => $v->requirements->map(fn ($r) => [
                'ecosystem' => $r->ecosystem,
                'name' => $r->name,
                'version' => $r->version_constraint,
            ])->values()->all(),
            'files' => $v->files->sortBy('path')->map(fn ($f) => [
                'path' => $f->path,
                'size' => $f->size,
                'sha256' => $f->sha256,
            ])->values()->all(),
        ]);
    }

    private function find(Request $request, string $author, string $module): Module
    {
        $found = Module::query()
            ->whereHas('author', fn ($q) => $q->where('handle', $author))
            ->where('name', $module)
            ->with(['author', 'latestVersion'])
            ->first();

        /** @var User|null $user */
        $user = $request->user();
        abort_if($found === null || ! $found->isVisibleTo($user), 404);

        return $found;
    }
}
