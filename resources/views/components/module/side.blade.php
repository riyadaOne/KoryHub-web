{{--
    Colonne latérale de la fiche (maquette exemple-kory.html) : commande kory pull, dernière version,
    empreinte, catégorie, étiquettes, auteur. Téléchargements et notes s'ajoutent en W04 et W05.
--}}
@props(['module', 'version' => null])

<aside {{ $attributes->class(["grid min-w-0 content-start gap-5"]) }} aria-label="{{ __('modules.side.label') }}">
    <div class="grid gap-3.5 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <x-copy-command :command="'kory pull @'.$module->author->handle.'/'.$module->name" dir="ltr" />

        <dl class="m-0 grid gap-2.5 text-sm">
            @if ($version)
                <div class="grid gap-0.5">
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.side.latest') }}</dt>
                    <dd class="m-0 font-mono" dir="ltr">{{ $version->version }} · <x-relative-date :at="$version->published_at" /></dd>
                </div>
                <div class="grid gap-0.5">
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.side.checksum') }}</dt>
                    <dd class="m-0 break-all font-mono" dir="ltr" title="{{ $version->archive_sha256 }}">{{ substr($version->archive_sha256, 0, 12) }}…{{ substr($version->archive_sha256, -4) }}</dd>
                </div>
            @endif
            @if ($version && $version->requirements->isNotEmpty())
                <div class="grid gap-0.5">
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.side.requirements') }}</dt>
                    <dd class="m-0 grid gap-0.5 font-mono text-[13px]" dir="ltr" data-test="requirements">
                        @foreach ($version->requirements as $requirement)
                            <span>{{ $requirement->ecosystem ? $requirement->ecosystem.' · ' : '' }}{{ $requirement->name }}{{ $requirement->version_constraint ? ' '.$requirement->version_constraint : '' }}</span>
                        @endforeach
                    </dd>
                </div>
            @endif
            @if ($module->category)
                <div class="grid gap-0.5">
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.side.category') }}</dt>
                    <dd class="m-0">{{ $module->category->name() }}</dd>
                </div>
            @endif
            @if ($module->tags->isNotEmpty())
                <div class="grid gap-1">
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.side.tags') }}</dt>
                    <dd class="m-0 flex flex-wrap gap-1.5" dir="ltr">
                        @foreach ($module->tags as $tag)
                            <span class="rounded-full border border-zinc-200 bg-zinc-100 px-2 text-xs dark:border-zinc-700 dark:bg-zinc-800">{{ $tag->name }}</span>
                        @endforeach
                    </dd>
                </div>
            @endif
            <div class="grid gap-0.5">
                <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.side.author') }}</dt>
                <dd class="m-0"><span class="font-mono" dir="ltr">{{ '@'.$module->author->handle }}</span>@if ($module->author->country) · {{ app(\App\Services\Accounts\Countries::class)->name($module->author->country) }}@endif</dd>
            </div>
        </dl>
    </div>
</aside>
