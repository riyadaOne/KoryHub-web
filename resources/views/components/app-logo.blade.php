{{-- Logo KoryHub : symbole sarcelle + « Kory » (texte) + « Hub » (sarcelle), UXUI §17. --}}
@props([
    'sidebar' => false,
])

@php($tag = $attributes->has('href') ? 'a' : 'span')

<{{ $tag }} {{ $attributes->class('inline-flex items-center gap-2 font-semibold tracking-tight text-zinc-900 dark:text-zinc-50') }}>
    <x-app-logo-icon class="{{ $sidebar ? 'size-7' : 'size-8' }} shrink-0 text-accent" />
    <span class="text-lg" dir="ltr">Kory<span class="text-accent">Hub</span></span>
</{{ $tag }}>
