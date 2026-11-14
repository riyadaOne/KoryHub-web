<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Support\Docs\DocsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;

/**
 * Documentation docs.koryhub.dev (S-13, W09) : accueil et pages par langue (/{langue}/{page}),
 * anglais en secours, 404 pour une page inconnue. La langue de l'adresse devient celle de la page.
 * Pages publiques S-10 à S-12 (/governance, /terms, /privacy) dans la langue choisie sur le site.
 */
class DocsController extends Controller
{
    public const PUBLIC_PAGES = ['governance', 'terms', 'privacy'];

    public function home(?string $locale = null): View
    {
        $locale = $this->useLocale($locale);

        return view('docs.home', ['locale' => $locale, 'navigation' => $this->navigation($locale)]);
    }

    public function show(string $locale, string $page): View
    {
        $locale = $this->useLocale($locale);
        abort_unless(in_array($page, DocsRepository::slugs(), true), 404);
        $doc = DocsRepository::docs()->find($locale, $page) ?? abort(404);

        $slugs = DocsRepository::slugs();
        $index = array_search($page, $slugs, true);
        $titles = fn (?string $slug) => $slug === null ? null : ['slug' => $slug, 'title' => DocsRepository::docs()->title($locale, $slug)];

        return view('docs.page', [
            'locale' => $locale,
            'doc' => $doc,
            'navigation' => $this->navigation($locale),
            'previous' => $titles($slugs[$index - 1] ?? null),
            'next' => $titles($slugs[$index + 1] ?? null),
        ]);
    }

    public function publicPage(string $page): View
    {
        abort_unless(in_array($page, self::PUBLIC_PAGES, true), 404);
        $doc = DocsRepository::publicPages()->find(Locale::current(), $page) ?? abort(404);

        return view('public.page', ['doc' => $doc]);
    }

    private function useLocale(?string $value): Locale
    {
        $locale = $value === null ? Locale::current() : (Locale::tryFrom($value) ?? abort(404));
        App::setLocale($locale->value);

        return $locale;
    }

    /** @return array<string, list<array{slug: string, title: string}>> */
    private function navigation(Locale $locale): array
    {
        $repository = DocsRepository::docs();

        return collect(DocsRepository::navigation())->map(fn (array $pages) => collect($pages)
            ->map(fn (string $slug) => ['slug' => $slug, 'title' => $repository->title($locale, $slug) ?? $slug])
            ->all())->all();
    }
}
