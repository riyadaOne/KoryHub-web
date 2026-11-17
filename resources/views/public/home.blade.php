{{--
    S-01 Accueil (F-RCH-12, maquettes W05) : recherche, commande d'installation du CLI (TRD §18),
    « Mis en avant » seulement si l'admin a coché des modules (décision W05 4a), récemment mis à
    jour, catégories avec leur nombre de modules.
--}}
<x-layouts::public>
    <div class="relative py-12">
        <section class="mx-auto grid w-full max-w-[720px] justify-items-center gap-4 text-center">
            <x-app-logo-icon class="size-16 text-accent" />
            <flux:heading size="xl" level="1" class="text-balance">{{ __('common.home_title') }}</flux:heading>
            <flux:text size="lg" class="text-balance">{{ __('common.home_lead') }}</flux:text>
            <form method="GET" action="{{ route('search') }}" class="flex w-full max-w-xl gap-2" role="search">
                <flux:input name="q" icon="magnifying-glass" :placeholder="__('search.placeholder')" :aria-label="__('search.placeholder')" class="flex-1" data-test="home-search" />
                <flux:button type="submit" variant="primary">{{ __('search.submit') }}</flux:button>
            </form>
            <x-copy-command :command="config('koryhub.cli.install_windows')" class="w-full max-w-xl" data-test="install-command" />
            <flux:text class="text-sm">{{ __('discover.install_other') }} <code dir="ltr">{{ config('koryhub.cli.install_unix') }}</code></flux:text>
        </section>

        @if ($cadev)
            {{-- Bloc CADEV 2026 (jury) : petite carte inclinee dans la marge, a cote de la recherche (sous la recherche en dessous de 1280 px) ; se redresse au survol ; masquee apres config('koryhub.cadev.until'). --}}
            <aside class="mx-auto mt-8 grid w-full max-w-[300px] gap-2 rounded-xl border border-accent/40 bg-white p-2.5 shadow-lg shadow-accent/10 transition-transform duration-300 xl:absolute xl:top-10 xl:-end-12 xl:mt-0 xl:w-[200px] xl:rotate-[30deg] xl:hover:rotate-0 xl:hover:scale-125 xl:focus-within:rotate-0 xl:focus-within:scale-125 dark:bg-zinc-900" data-test="cadev">
                <div class="flex items-center justify-between gap-1">
                    <flux:badge color="teal" size="sm">{{ __('discover.cadev_badge') }}</flux:badge>
                    <span class="text-[11px] font-medium text-zinc-600 dark:text-zinc-300">{{ __('discover.cadev_video') }}</span>
                </div>
                <video controls preload="none" playsinline class="aspect-video w-full rounded-md bg-black" @if ($cadev['poster_url']) poster="{{ $cadev['poster_url'] }}" @endif data-test="cadev-video">
                    <source src="{{ $cadev['video_url'] }}" type="video/mp4" />
                    <a href="{{ $cadev['video_url'] }}">{{ __('discover.cadev_download') }}</a>
                </video>
                <div class="grid gap-1">
                    <span class="text-[10px] font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ __('discover.cadev_team') }}</span>
                    @foreach ($cadev['team'] as $member)
                        <a href="https://github.com/{{ $member['github'] }}" target="_blank" rel="noopener" class="grid rounded-md px-1.5 py-1 text-xs hover:bg-zinc-100 dark:hover:bg-zinc-800" title="{{ $member['role'] === 'founder' ? __('discover.cadev_role_founder') : __('discover.cadev_role_frontend') }}" data-test="cadev-member">
                            <span class="font-medium">{{ $member['name'] }}</span>
                            <span class="text-[11px] text-accent-content" dir="ltr">{{ 'github.com/'.$member['github'] }}</span>
                        </a>
                    @endforeach
                </div>
            </aside>
        @endif
    </div>

    <div class="grid gap-8 pb-12">
        @if ($featured->isNotEmpty())
            <section class="grid gap-3" data-test="featured">
                <flux:heading size="lg" level="2">{{ __('discover.featured') }}</flux:heading>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-3.5">
                    @foreach ($featured as $module)
                        <x-module.card :module="$module" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($recent->isNotEmpty())
            <section class="grid gap-3" data-test="recent">
                <div class="flex items-baseline justify-between gap-2">
                    <flux:heading size="lg" level="2">{{ __('discover.recent') }}</flux:heading>
                    <flux:link :href="route('search', ['sort' => 'recent'])" wire:navigate>{{ __('discover.see_all') }}</flux:link>
                </div>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-3.5">
                    @foreach ($recent as $module)
                        <x-module.card :module="$module" />
                    @endforeach
                </div>
            </section>
        @endif

        <section class="grid gap-3" data-test="categories">
            <flux:heading size="lg" level="2">{{ __('discover.categories') }}</flux:heading>
            <div class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <a href="{{ route('categories.show', $category['slug']) }}" class="rounded-full border border-zinc-200 bg-white px-3 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900" wire:navigate>
                        {{ $category['name'] }} <span class="ms-1 text-zinc-500 dark:text-zinc-400">{{ $category['count'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts::public>
