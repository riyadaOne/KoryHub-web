{{-- S-09 Catégorie (F-RCH-11) : nom et description dans la langue du site, modules publics par score, filtre de langage. --}}
@php($description = $category->translations->firstWhere('locale', app()->getLocale())?->description ?? $category->translations->firstWhere('locale', 'en')?->description)
<x-layouts::public :title="$category->name()" :description="$description">
    <section class="grid gap-5 py-6">
        <div>
            <flux:heading size="xl" level="1">{{ $category->name() }}</flux:heading>
            @if ($description)
                <flux:text class="mt-1">{{ $description }}</flux:text>
            @endif
        </div>

        @if ($languages->count() > 1)
            <nav class="flex flex-wrap gap-2 text-sm" aria-label="{{ __('search.language') }}">
                @php($active = 'rounded-full border border-accent px-3 py-1 font-semibold')
                @php($idle = 'rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700')
                <a href="{{ route('categories.show', $category->slug) }}" class="{{ $language === '' ? $active : $idle }}" wire:navigate>{{ __('search.all') }}</a>
                @foreach ($languages as $lang)
                    <a href="{{ route('categories.show', ['slug' => $category->slug, 'language' => $lang]) }}" class="{{ $language === $lang ? $active : $idle }}" wire:navigate>{{ $lang }}</a>
                @endforeach
            </nav>
        @endif

        @if ($modules->isEmpty())
            <x-empty-state icon="squares-2x2" :title="__('discover.category_empty')" data-test="category-empty" />
        @else
            <div class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-3.5" data-test="category-modules">
                @foreach ($modules as $module)
                    <x-module.card :module="$module" />
                @endforeach
            </div>
            <div>{{ $modules->links() }}</div>
        @endif
    </section>
</x-layouts::public>
