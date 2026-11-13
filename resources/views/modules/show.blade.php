{{-- S-03 Fiche d'un kory : README rendu et colonne latérale (maquette exemple-kory.html). --}}
<x-layouts::app :title="'@'.$module->author->handle.'/'.$module->name" :description="$module->description">
    <x-module.header :module="$module" tab="readme" :version="$version" :is-author="$isAuthor" :files-count="$filesCount" :versions-count="$versionsCount" />

    <div class="grid items-start gap-8 py-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <article class="readme min-w-0 max-w-[72ch]" data-test="readme">
            @if ($version)
                {{ \App\Support\CodeView::markdown($version->readme) }}
            @else
                <x-empty-state icon="document-text" :title="__('modules.no_published_version')" />
            @endif
        </article>

        <x-module.side :module="$module" :version="$version" class="max-lg:order-first" />
    </div>
</x-layouts::app>
