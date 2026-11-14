{{--
    Navigation de la documentation (S-13, maquettes W09) : groupes et pages dans l'ordre de
    resources/docs/nav.php ; page en cours marquée ; filtre par titre. Sur téléphone, repliée
    dans « Sommaire ».
--}}
@props(['navigation', 'locale', 'current' => null])

<nav x-data="{ open: false, q: '' }" class="text-sm" aria-label="{{ __('docs.nav') }}" data-test="docs-nav">
    <button type="button" class="mb-2 flex w-full items-center justify-between rounded-md border border-zinc-200 px-3 py-2 lg:hidden dark:border-zinc-700" x-on:click="open = ! open" x-bind:aria-expanded="open">
        {{ __('docs.contents') }} <flux:icon name="chevron-down" class="size-4" />
    </button>
    <div class="hidden gap-1 lg:grid" x-bind:class="open ? 'grid!' : ''">
        <flux:input size="sm" icon="magnifying-glass" x-model="q" :placeholder="__('docs.filter')" :aria-label="__('docs.filter')" class="mb-2" />
        @foreach ($navigation as $group => $pages)
            <div class="px-2.5 pt-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ __("docs.groups.{$group}") }}</div>
            @foreach ($pages as $page)
                <a href="{{ route('docs.page', ['locale' => $locale->value, 'page' => $page['slug']]) }}"
                    x-show="q === '' || @js(mb_strtolower($page['title'])).includes(q.toLowerCase())"
                    @if ($current === $page['slug']) aria-current="page" @endif
                    class="rounded-md px-2.5 py-1.5 hover:bg-zinc-100 aria-[current=page]:bg-accent/15 aria-[current=page]:font-semibold dark:hover:bg-zinc-800">{{ $page['title'] }}</a>
            @endforeach
        @endforeach
    </div>
</nav>
