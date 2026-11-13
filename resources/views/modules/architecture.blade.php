{{--
    S-14 Architecture (F-RCH-18, maquette exemple-kory.html) : diagramme Mermaid construit par le serveur,
    rendu en mode strict, chargé à l'ouverture de l'onglet ; clic sur un composant = son fichier (S-06).
    La liste des liens est l'équivalent texte ; elle passe en premier sous 640 px.
--}}
<x-layouts::app :title="'@'.$module->author->handle.'/'.$module->name">
    <x-module.header :module="$module" tab="architecture" :version="$version" :is-author="$isAuthor" :files-count="$filesCount" :versions-count="$versionsCount" />

    <div class="grid gap-4 py-6">
        @if (! $diagram || $componentsCount === 0)
            <x-empty-state icon="share" :title="__('modules.architecture.none')" :description="__('modules.architecture.none_text')" data-test="no-architecture" />
        @else
            @php($filesUrl = route('modules.files', ['author' => $module->author->handle, 'module' => $module->name]))
            <p class="m-0 text-sm text-zinc-500 dark:text-zinc-400">{{ __('modules.architecture.summary', ['components' => $componentsCount, 'links' => count($diagram['lines'])]) }}</p>

            <div class="flex flex-col gap-4">
                <div class="order-2 overflow-x-auto rounded-lg border border-zinc-200 bg-white p-3 max-sm:order-2 sm:order-1 dark:border-zinc-700 dark:bg-zinc-900" dir="ltr"
                     data-architecture
                     data-files="{{ json_encode($diagram['files']) }}"
                     data-files-url="{{ $filesUrl }}"
                     data-version="{{ $version->version }}"
                     data-test="architecture-diagram">
                    <pre class="hidden" data-mermaid-source>{{ $diagram['text'] }}</pre>
                    <div data-mermaid-target class="min-w-[640px]"></div>
                </div>

                <section class="order-1 sm:order-2">
                    <h2 class="m-0 mb-1 text-[15px] font-semibold">{{ __('modules.architecture.list') }}</h2>
                    <ul class="m-0 grid list-none gap-1 p-0 text-sm" data-test="architecture-list">
                        @foreach ($diagram['lines'] as $line)
                            <li>
                                <a href="{{ $filesUrl.'?'.http_build_query(['path' => $line['from_file'], 'v' => $version->version]) }}" class="font-mono" dir="ltr">{{ $line['from'] }}</a>
                                {{ $line['kind'] }}
                                <a href="{{ $filesUrl.'?'.http_build_query(['path' => $line['to_file'], 'v' => $version->version]) }}" class="font-mono" dir="ltr">{{ $line['to'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-[13px] text-zinc-500 dark:text-zinc-400">{{ __('modules.architecture.legend') }}</p>
                </section>
            </div>
        @endif
    </div>
</x-layouts::app>
