<?php

namespace App\Services\Search;

use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Models\Category;
use App\Models\Module;
use App\Models\ModuleVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche de kory (W05.2, F-RCH-01, F-RCH-03 à F-RCH-05), commune au site (S-02) et à l'API
 * (GET /v1/search) : modules publics ayant au moins une version publiée ou dépréciée ; mots
 * cherchés par Scout (index plein texte MySQL, requêtes liées : aucune injection possible) ;
 * filtres catégorie, langage, certifiés ; tri pertinence puis score, ou téléchargements, note,
 * récence.
 */
class ModuleSearch
{
    public const SORTS = ['relevance', 'downloads', 'rating', 'recent'];

    /**
     * @param  array{q?: ?string, category?: ?string, language?: ?string, certified?: bool, sort?: ?string}  $filters
     */
    public function search(array $filters, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $words = $this->clean($filters['q'] ?? '');
        $sort = in_array($filters['sort'] ?? null, self::SORTS, true) ? $filters['sort'] : 'relevance';

        $constrain = function (Builder $query) use ($filters, $words, $sort): void {
            $this->constrain($query, $filters);
            $this->order($query, $words, $sort);
            $query->with(['author', 'latestVersion', 'category.translations']);
        };

        if ($words === '') {
            $query = Module::query();
            $constrain($query);

            return $query->paginate($perPage, page: $page);
        }

        return Module::search($words)->query($constrain)->paginate($perPage, 'page', $page);
    }

    /** Nombre de résultats par catégorie, sans le filtre de catégorie (filtres de S-02). */
    public function categoryCounts(array $filters): array
    {
        $words = $this->clean($filters['q'] ?? '');
        $ids = $words === ''
            ? null
            : Module::search($words)->query(fn (Builder $q) => $this->constrain($q, ['category' => null] + $filters))->keys()->all();

        $query = Module::query()->selectRaw('category_id, COUNT(*) as total')->groupBy('category_id');
        $this->constrain($query, ['category' => null] + $filters);
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->pluck('total', 'category_id')->all();
    }

    /** Requête assainie : espaces normalisés, longueur bornée (les valeurs restent liées à la requête SQL). */
    public function clean(?string $words): string
    {
        $words = trim(preg_replace('/\s+/u', ' ', (string) $words) ?? '');

        return mb_substr($words, 0, (int) config('koryhub.search.max_query'));
    }

    private function constrain(Builder $query, array $filters): void
    {
        $query->where('modules.visibility', Visibility::Public)
            ->whereHas('versions', fn (Builder $v) => $v->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated]));

        if (! empty($filters['category'])) {
            $query->whereIn('category_id', Category::query()->select('id')->where('slug', $filters['category']));
        }
        if (! empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }
        if (! empty($filters['certified'])) {
            $query->whereRaw('1 = 0'); // Certification en W06 : aucun module certifié pour l'instant.
        }
    }

    private function order(Builder $query, string $words, string $sort): void
    {
        match ($sort) {
            'downloads' => $query->orderByDesc('download_count'),
            'rating' => $query->orderByRaw('rating_avg IS NULL')->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            'recent' => $query->orderByDesc(
                ModuleVersion::query()->select('published_at')->whereColumn('module_versions.id', 'modules.latest_version_id')->limit(1)
            ),
            default => $words === '' ? null : $query
                ->orderByRaw('modules.name = ? desc', [str_replace(' ', '-', mb_strtolower($words))])
                ->orderByRaw('MATCH(modules.description, modules.search_text) AGAINST (? IN NATURAL LANGUAGE MODE) desc', [$words]),
        };
        $query->orderByDesc('score')->orderBy('modules.name');
    }
}
