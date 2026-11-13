{{-- S-05 Détail d'une version : statut, empreinte complète, licence, contenu ; motif du rejet pour l'auteur. --}}
<x-layouts::app :title="'@'.$module->author->handle.'/'.$module->name.' '.$version->version">
    <x-module.header :module="$module" tab="versions" :version="$version" :is-author="$isAuthor" :files-count="$filesCount" :versions-count="$versionsCount" />

    <div class="grid max-w-3xl gap-4 py-6">
        @if ($version->status === \App\Enums\VersionStatus::Rejected)
            <x-warning-banner variant="danger" :heading="$version->rejection_reason" data-test="rejection-banner">
                {{ __('modules.version.resend', ['version' => $version->version]) }}
            </x-warning-banner>
        @endif

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <dl class="m-0 grid gap-3 text-sm">
                @if ($version->status !== \App\Enums\VersionStatus::Rejected)
                    <div class="grid gap-1">
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.side.checksum') }}</dt>
                        <dd class="m-0"><x-copy-command :command="$version->archive_sha256" dir="ltr" data-test="version-checksum" /></dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.version.published') }}</dt>
                        <dd class="m-0"><x-relative-date :at="$version->published_at" /> · <span class="font-mono" dir="ltr">{{ '@'.$version->publisher->handle }}</span></dd>
                    </div>
                @else
                    <div class="grid gap-1">
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.version.submitted') }}</dt>
                        <dd class="m-0"><x-relative-date :at="$version->submitted_at" /> · <span class="font-mono">{{ $version->rejection_code }}</span></dd>
                    </div>
                @endif
                <div class="grid gap-1">
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.version.license_language') }}</dt>
                    <dd class="m-0">{{ $version->license->spdx() }}@if ($version->language) · {{ $version->language }}@endif</dd>
                </div>
                @if ($version->status !== \App\Enums\VersionStatus::Rejected)
                    <div class="grid gap-1">
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.version.content') }}</dt>
                        <dd class="m-0">{{ __('modules.version.files_size', ['count' => $version->files_count, 'size' => max(1, (int) ceil($version->archive_size / 1024))]) }}</dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.version.install') }}</dt>
                        <dd class="m-0"><x-copy-command :command="'kory pull @'.$module->author->handle.'/'.$module->name.'@'.$version->version" dir="ltr" /></dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</x-layouts::app>
