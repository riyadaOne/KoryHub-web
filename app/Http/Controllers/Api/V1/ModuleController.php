<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Module vu par le CLI (kory list, kory push) ; un module privé d'un autre compte répond 404.
 */
class ModuleController extends Controller
{
    /**
     * Un module et sa dernière version.
     */
    public function show(Request $request, string $author, string $module): JsonResponse
    {
        $found = Module::query()
            ->whereHas('author', fn ($q) => $q->where('handle', $author))
            ->where('name', $module)
            ->with(['author', 'latestVersion'])
            ->first();

        /** @var User $user */
        $user = $request->user();
        abort_if($found === null || ! $found->isVisibleTo($user), 404);

        return response()->json([
            /** Nom complet @auteur/nom. */
            'name' => $found->fullName(),
            /** public ou private. */
            'visibility' => $found->visibility->value,
            /** active ou archived. */
            'status' => $found->status->value,
            /** Dernière version stable publiée, ou null. */
            'latest_version' => $found->latestVersion?->version,
        ]);
    }
}
