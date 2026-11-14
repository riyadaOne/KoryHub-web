{{-- Pages publiques S-10 Gouvernance, S-11 Conditions, S-12 Confidentialité (W09) : une colonne de lecture, texte en Markdown dans resources/pages/{langue}. --}}
<x-layouts::public :title="$doc->title">
    <article class="mx-auto grid max-w-[72ch] gap-4 py-8" data-test="public-page" lang="{{ $doc->locale->value }}" dir="{{ $doc->locale->direction() }}">
        @if ($doc->fallback)
            <flux:callout icon="language" data-test="docs-fallback"><flux:callout.heading>{{ __('docs.not_translated') }}</flux:callout.heading></flux:callout>
        @endif
        <flux:heading size="xl" level="1">{{ $doc->title }}</flux:heading>
        <div class="readme">{{ $doc->html }}</div>
    </article>
</x-layouts::public>
