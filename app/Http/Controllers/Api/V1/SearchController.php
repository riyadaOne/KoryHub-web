<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VersionStatus;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\Search\ModuleSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche pour kory search (W05.2, F-RCH-01, F-RCH-05) : mêmes règles que S-02.
 */
class SearchController extends Controller
{
    /**
     * Chercher des kory.
     *
     * Modules publics seulement, triés par pertinence puis score. `limit` : 20 par défaut, 100 au plus.
     */
    public function __invoke(Request $request, ModuleSearch $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:64'],
            'language' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'in:'.implode(',', ModuleSearch::SORTS)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('koryhub.search.api_max')],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $results = $search->search($validated, (int) ($validated['limit'] ?? 20), (int) ($validated['page'] ?? 1));

        return response()->json([
            /** Nombre total de résultats. */
            'total' => $results->total(),
            'results' => collect($results->items())->map(fn (Module $m) => [
                'name' => $m->fullName(),
                'description' => $m->description,
                'language' => $m->language,
                'category' => $m->category?->slug,
                /** Dernière version stable, ou null. */
                'latest_version' => $m->latestVersion?->version,
                /** La dernière version est dépréciée. */
                'deprecated' => $m->latestVersion?->status === VersionStatus::Deprecated,
                'archived' => $m->status->value === 'archived',
                'certified' => false,
                /** Note vérifiée moyenne, ou null (avis masqués : null). */
                'rating_avg' => $m->reviews_hidden ? null : $m->rating_avg,
                'rating_count' => $m->reviews_hidden ? 0 : $m->rating_count,
                'download_count' => $m->download_count,
            ])->values()->all(),
        ]);
    }
}
