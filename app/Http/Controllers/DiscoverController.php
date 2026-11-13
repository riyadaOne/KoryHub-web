<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\User;
use App\Services\Accounts\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Découverte (W05 bloc C) : accueil S-01, catégorie S-09, profil public S-08. Modules publics
 * seulement, classés par score ; jamais de module privé ni de donnée privée du compte.
 */
class DiscoverController extends Controller
{
    /** S-01 : recherche, installation du CLI, mis en avant (si l'admin en a coché), récents, catégories. */
    public function home(): View
    {
        $featured = Module::query()->listed()
            ->where('is_featured', true)->where('reviews_hidden', false)
            ->latest('featured_at')->limit(6)->get();

        $recent = Module::query()->listed()
            ->orderByDesc(ModuleVersion::query()->select('published_at')->whereColumn('module_versions.id', 'modules.latest_version_id')->limit(1))
            ->limit(6)->get();

        return view('public.home', [
            'featured' => $featured,
            'recent' => $recent,
            'categories' => $this->categoriesWithCounts(),
        ]);
    }

    /** S-09 : modules publics d'une catégorie, par score ; filtre de langage. */
    public function category(Request $request, string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->where('is_active', true)->with('translations')->firstOrFail();
        $language = (string) $request->query('language', '');

        $modules = Module::query()->listed()
            ->where('category_id', $category->id)
            ->when($language !== '', fn ($q) => $q->where('language', $language))
            ->orderByDesc('score')->orderBy('name')
            ->paginate(20)->withQueryString();

        $languages = Module::query()->listed()->where('category_id', $category->id)
            ->whereNotNull('language')->distinct()->orderBy('language')->pluck('language');

        return view('public.category', compact('category', 'modules', 'language', 'languages'));
    }

    /** S-08 : profil public ; compte supprimé : « n'existe plus » (le nom reste réservé). */
    public function profile(string $author, Countries $countries): View
    {
        $user = User::query()->where('handle', $author)->firstOrFail();
        if ($user->status === AccountStatus::Deleted) {
            return view('public.profile-deleted', ['handle' => $user->handle]);
        }

        $modules = Module::query()->listed()->where('author_id', $user->id)
            ->orderByDesc('score')->orderBy('name')->get();

        return view('public.profile', [
            'user' => $user,
            'modules' => $modules,
            'country' => $user->country ? $countries->name($user->country) : null,
            'downloads' => (int) $modules->sum('download_count'),
        ]);
    }

    /** @return Collection<int, array{slug: string, name: string, count: int}> */
    private function categoriesWithCounts()
    {
        $counts = Module::query()->listed()->selectRaw('category_id, COUNT(*) as total')->groupBy('category_id')
            ->setEagerLoads([])->pluck('total', 'category_id');

        return Category::query()->where('is_active', true)->orderBy('position')->with('translations')->get()
            ->map(fn (Category $c) => ['slug' => $c->slug, 'name' => $c->name(), 'count' => (int) ($counts[$c->id] ?? 0)]);
    }
}
