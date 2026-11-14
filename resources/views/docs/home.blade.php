{{-- Accueil de la documentation (S-13, maquettes W09) : commande d'installation copiable, puis les pages en cartes, dans l'ordre de la navigation. --}}
<x-layouts::public :title="__('docs.title')" :description="__('docs.tagline')">
    <div class="grid gap-8 py-6 lg:grid-cols-[220px_minmax(0,1fr)]">
        <x-docs.nav :navigation="$navigation" :locale="$locale" />

        <div class="grid min-w-0 content-start gap-6" data-test="docs-home">
            <div>
                <flux:heading size="xl" level="1">{{ __('docs.home_title') }}</flux:heading>
                <flux:text class="mt-1">{{ __('docs.tagline') }}</flux:text>
            </div>
            <div class="grid max-w-xl gap-2">
                <x-copy-command :command="config('koryhub.cli.install_windows')" />
                <x-copy-command :command="config('koryhub.cli.install_unix')" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($navigation as $pages)
                    @foreach ($pages as $page)
                        <a href="{{ route('docs.page', ['locale' => $locale->value, 'page' => $page['slug']]) }}" class="rounded-lg border border-zinc-200 bg-white p-4 font-semibold hover:border-accent dark:border-zinc-700 dark:bg-zinc-900">{{ $page['title'] }}</a>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</x-layouts::public>
