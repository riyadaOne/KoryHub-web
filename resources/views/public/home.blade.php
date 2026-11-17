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
            {{-- Bloc CADEV 2026 (jury) : carte inclinee a 12 degres dans la marge droite, la recherche restant centree (360 px a partir de 1536 px, 240 px de 1280 a 1535 px, sous la recherche en dessous) ; se redresse au survol ; masquee apres config('koryhub.cadev.until'). --}}
            <aside class="mx-auto mt-8 grid w-full max-w-[420px] gap-3 rounded-2xl border border-accent/40 bg-white p-4 shadow-xl shadow-accent/10 transition-transform duration-300 xl:absolute xl:top-6 xl:-end-12 xl:mt-0 xl:w-[240px] xl:gap-2 xl:p-3 2xl:-end-[130px] 2xl:w-[360px] 2xl:gap-3 2xl:p-4 xl:rotate-[12deg] xl:hover:rotate-0 xl:focus-within:rotate-0 dark:bg-zinc-900" data-test="cadev">
                <div class="flex items-center justify-between gap-2">
                    <flux:badge color="teal" size="sm">{{ __('discover.cadev_badge') }}</flux:badge>
                    <flux:text class="text-xs">{{ __('discover.cadev_label') }}</flux:text>
                </div>
                <flux:heading size="lg" level="2">{{ __('discover.cadev_video') }}</flux:heading>
                <video controls preload="none" playsinline class="aspect-video w-full rounded-lg bg-black" @if ($cadev['poster_url']) poster="{{ $cadev['poster_url'] }}" @endif data-test="cadev-video">
                    <source src="{{ $cadev['video_url'] }}" type="video/mp4" />
                    <a href="{{ $cadev['video_url'] }}">{{ __('discover.cadev_download') }}</a>
                </video>
                <div class="grid gap-2">
                    <flux:text class="text-xs font-semibold uppercase">{{ __('discover.cadev_team') }}</flux:text>
                    @foreach ($cadev['team'] as $member)
                        <a href="https://github.com/{{ $member['github'] }}" target="_blank" rel="noopener" class="flex flex-wrap items-center justify-between gap-x-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:border-accent dark:border-zinc-700" data-test="cadev-member">
                            <span class="grid">
                                <span class="font-medium">{{ $member['name'] }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $member['role'] === 'founder' ? __('discover.cadev_role_founder') : __('discover.cadev_role_frontend') }}</span>
                            </span>
                            <span class="text-xs text-accent-content" dir="ltr">{{ 'github.com/'.$member['github'] }}</span>
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
