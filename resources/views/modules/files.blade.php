{{-- S-06 Fichiers d'une version : arborescence et fichier choisi, coloré (maquette exemple-kory.html). --}}
<x-layouts::app :title="'@'.$module->author->handle.'/'.$module->name" :description="$module->description">
    <x-module.header :module="$module" tab="files" :version="$version" :is-author="$isAuthor" :files-count="$filesCount" :versions-count="$versionsCount" />

    @if ($files->isEmpty())
        <x-empty-state icon="folder" :title="__('modules.no_published_version')" class="mt-6" />
    @else
        @php($base = route('modules.files', ['author' => $module->author->handle, 'module' => $module->name]))
        <div class="grid items-start gap-4 py-6 md:grid-cols-[260px_minmax(0,1fr)]">
            <nav class="rounded-lg border border-zinc-200 bg-white py-2 font-mono text-[13px] dark:border-zinc-700 dark:bg-zinc-900" aria-label="{{ __('modules.files.tree', ['version' => $version->version]) }}" dir="ltr" data-test="file-tree">
                @php($previous = [])
                @foreach ($files as $file)
                    @php($parts = explode('/', $file->path))
                    @php($dirs = array_slice($parts, 0, -1))
                    @foreach ($dirs as $depth => $dir)
                        @if (($previous[$depth] ?? null) !== $dir)
                            <div class="px-3 py-1 text-zinc-500 dark:text-zinc-400" style="padding-inline-start: {{ 12 + $depth * 14 }}px">{{ $dir }}/</div>
                        @endif
                    @endforeach
                    @php($previous = $dirs)
                    <a href="{{ $base.'?'.http_build_query(['path' => $file->path, 'v' => $version->version]) }}" wire:navigate
                       @class(['block truncate py-1 pe-3 hover:bg-zinc-100 dark:hover:bg-zinc-800', 'bg-accent/10 font-semibold' => $selected?->is($file)])
                       style="padding-inline-start: {{ 12 + count($dirs) * 14 }}px" @if ($selected?->is($file)) aria-current="true" @endif>{{ end($parts) }}</a>
                @endforeach
            </nav>

            @if ($selected)
                <div class="min-w-0">
                    <div class="flex flex-wrap justify-between gap-3 rounded-t-lg border border-b-0 border-zinc-200 bg-white px-3 py-2 text-[13px] text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                        <span class="font-mono text-zinc-900 dark:text-zinc-50" dir="ltr" data-test="file-name">{{ $selected->path }}</span>
                        <span dir="ltr" title="{{ $selected->sha256 }}">{{ __('modules.files.meta', ['size' => max(1, (int) ceil($selected->size / 1024))]) }} · SHA-256 {{ substr($selected->sha256, 0, 12) }}…</span>
                    </div>
                    <div class="overflow-x-auto rounded-b-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" dir="ltr">
                        @if ($selected->is_image)
                            <img src="{{ route('modules.raw', ['author' => $module->author->handle, 'module' => $module->name, 'number' => $version->version, 'path' => $selected->path]) }}" alt="{{ $selected->path }}" class="m-4 max-w-full">
                        @elseif ($content === null)
                            <p class="m-0 p-4 text-sm text-zinc-500" dir="auto">{{ __('modules.files.too_large') }}</p>
                        @else
                            <pre class="m-0 p-4 text-[13px] leading-relaxed" data-test="file-code"><code>{{ \App\Support\CodeView::highlight($selected->path, $content) }}</code></pre>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-layouts::app>
