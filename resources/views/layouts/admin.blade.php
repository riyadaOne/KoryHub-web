{{--
    Administration (UXUI §5.3) : menu latéral et mention « Administration » dans l'en-tête,
    pour ne jamais confondre avec le site public. Les entrées du menu s'ajoutent module par module.
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Enums\Locale::current()->direction() }}" class="dark">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-50">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" :href="route('admin.home')" />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:badge color="teal" size="sm" class="self-start" data-test="admin-badge">{{ __('admin.title') }}</flux:badge>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('admin.home')" :current="request()->routeIs('admin.home')">
                    {{ __('common.dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.group :heading="__('admin.moderation')">
                    <flux:sidebar.item icon="flag" :href="route('admin.reports.index')" :current="request()->routeIs('admin.reports.*')" :badge="\App\Models\Report::query()->whereIn('status', \App\Enums\ReportStatus::open())->count() ?: null" data-test="admin-reports-link">
                        {{ __('moderation.admin.reports') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="check-badge" :href="route('admin.certifications.index')" :current="request()->routeIs('admin.certifications.*')" :badge="\App\Models\Certification::query()->whereIn('status', \App\Enums\CertificationStatus::open())->count() ?: null" data-test="admin-certifications-link">
                        {{ __('certification.admin.title') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                {{-- :current="false" : Flux compare les chemins, et « / » du site ressemble à « / » de l'administration. --}}
                <flux:sidebar.item icon="arrow-left" :href="route('home')" :current="false">{{ __('admin.back_to_site') }}</flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" :aria-label="__('common.menu')" />
            <flux:spacer />
            <flux:badge color="teal" size="sm">{{ __('admin.title') }}</flux:badge>
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
