{{--
    Page de la documentation (S-13, maquettes W09) : navigation, contenu (72 caractères par ligne),
    sommaire de la page ; page précédente et suivante. Page non traduite : bandeau, contenu anglais.
--}}
<x-layouts::public :title="$doc->title.' · '.__('docs.title')">
    <div class="grid gap-8 py-6 lg:grid-cols-[220px_minmax(0,1fr)] xl:grid-cols-[220px_minmax(0,1fr)_200px]">
        <x-docs.nav :navigation="$navigation" :locale="$locale" :current="$doc->slug" />

        <article class="min-w-0" data-test="docs-page" lang="{{ $doc->locale->value }}" dir="{{ $doc->locale->direction() }}">
            @if ($doc->fallback)
                <flux:callout icon="language" class="mb-4" data-test="docs-fallback"><flux:callout.heading>{{ __('docs.not_translated') }}</flux:callout.heading></flux:callout>
            @endif
            <flux:heading size="xl" level="1">{{ $doc->title }}</flux:heading>
            <div class="readme mt-4 max-w-[72ch]">{{ $doc->html }}</div>

            <div class="mt-8 flex max-w-[72ch] flex-wrap justify-between gap-3 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700" data-test="docs-pager">
                @if ($previous)
                    <a href="{{ route('docs.page', ['locale' => $locale->value, 'page' => $previous['slug']]) }}" class="text-accent">{{ __('docs.previous') }} · {{ $previous['title'] }}</a>
                @else
                    <span></span>
                @endif
                @if ($next)
                    <a href="{{ route('docs.page', ['locale' => $locale->value, 'page' => $next['slug']]) }}" class="text-accent">{{ $next['title'] }} · {{ __('docs.next') }}</a>
                @endif
            </div>
        </article>

        @if ($doc->headings !== [])
            <aside class="hidden content-start gap-1 text-sm xl:grid" data-test="docs-toc">
                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ __('docs.on_this_page') }}</span>
                @foreach ($doc->headings as $heading)
                    <a href="#{{ $heading['id'] }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50">{{ $heading['text'] }}</a>
                @endforeach
            </aside>
        @endif
    </div>
</x-layouts::public>
