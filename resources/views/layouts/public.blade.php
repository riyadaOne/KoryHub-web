{{--
    Mise en page publique (UXUI §5.1, maquette assets/maquettes/exemple-kory.html) :
    en-tête avec logo, recherche et connexion ; contenu ; pied de page avec le choix de la langue.
    Sert aussi à l'espace connecté (layouts/app) et à la documentation.
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}" class="dark">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="flex min-h-screen flex-col bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-50">
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-[1200px] flex-wrap items-center gap-4 px-4 py-3 lg:px-6">
                <x-app-logo :href="route('home')" />

                @if (Route::has('search'))
                    <form method="GET" action="{{ route('search') }}" class="order-last w-full sm:order-none sm:w-auto sm:flex-1" role="search">
                        <flux:input name="q" icon="magnifying-glass" :placeholder="__('common.search_placeholder')" :aria-label="__('common.search_placeholder')" />
                    </form>
                @else
                    <flux:spacer />
                @endif

                <nav class="flex items-center gap-4 text-sm" aria-label="{{ __('common.menu') }}">
                    <a href="{{ route('docs.home') }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50">
                        {{ __('common.documentation') }}
                    </a>

                    @auth
                        <livewire:notification-bell />

                        <flux:dropdown position="bottom" align="end">
                            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

                            <flux:menu>
                                <flux:menu.item :href="route('dashboard')" icon="layout-grid" wire:navigate>{{ __('common.dashboard') }}</flux:menu.item>
                                <flux:menu.item :href="route('modules.mine')" icon="cube" wire:navigate data-test="my-modules-link">{{ __('modules.mine.title') }}</flux:menu.item>
                                <flux:menu.item :href="route('stars.index')" icon="star" wire:navigate data-test="stars-link">{{ __('community.stars.title') }}</flux:menu.item>
                                <flux:menu.item :href="route('watching.index')" icon="eye" wire:navigate data-test="watching-link">{{ __('community.watch.title') }}</flux:menu.item>
                                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('common.settings') }}</flux:menu.item>
                                <flux:menu.separator />
                                <form method="POST" action="{{ route('logout') }}" class="w-full">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                                        {{ __('common.log_out') }}
                                    </flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    @else
                        <a href="{{ route('login') }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50">{{ __('common.log_in') }}</a>
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" size="sm">{{ __('common.register') }}</flux:button>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1200px] flex-1 px-4 py-8 lg:px-6">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-4 px-4 py-6 text-sm text-zinc-600 lg:px-6 dark:text-zinc-400">
                <a href="{{ route('docs.home') }}" class="hover:text-zinc-900 dark:hover:text-zinc-50">{{ __('common.documentation') }}</a>

                <div class="flex flex-wrap items-center gap-1" role="group" aria-label="{{ __('common.language') }}">
                    @foreach (\App\Enums\Locale::cases() as $locale)
                        <form method="POST" action="{{ route('locale.update', $locale) }}">
                            @csrf
                            <button type="submit"
                                lang="{{ $locale->value }}"
                                dir="{{ $locale->direction() }}"
                                @if ($locale === \App\Enums\Locale::current()) aria-current="true" @endif
                                class="rounded px-2 py-1 hover:text-zinc-900 aria-[current=true]:font-semibold aria-[current=true]:text-accent dark:hover:text-zinc-50"
                                data-test="locale-{{ $locale->value }}">
                                {{ $locale->nativeName() }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
