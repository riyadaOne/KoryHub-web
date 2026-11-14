{{-- S-05 Détail d'une version : statut, empreinte complète, licence, contenu ; motif du rejet pour l'auteur. --}}
<x-layouts::app :title="'@'.$module->author->handle.'/'.$module->name.' '.$version->version" :description="$module->description">
    <x-module.header :module="$module" tab="versions" :version="$version" :report-version="$version" :is-author="$isAuthor" :files-count="$filesCount" :versions-count="$versionsCount" />

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
                    {{-- Version retirée (W07) : plus téléchargeable, aucune commande d'installation. --}}
                    @if ($version->status !== \App\Enums\VersionStatus::Removed)
                        <div class="grid gap-1" data-test="install-version">
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('modules.version.install') }}</dt>
                            <dd class="m-0"><x-copy-command :command="'kory pull @'.$module->author->handle.'/'.$module->name.'@'.$version->version" dir="ltr" /></dd>
                        </div>
                    @endif
                @endif
            </dl>

            {{-- Certification (W06.3) : badge ou révocation, rapports PDF ; la page fait foi, le PDF n'est jamais modifié. --}}
            @php($decided = \App\Models\Certification::query()->where('version_id', $version->id)->whereIn('status', [\App\Enums\CertificationStatus::Granted, \App\Enums\CertificationStatus::Revoked])->with('reports')->latest('decided_at')->first())
            @if ($decided)
                <div class="mt-4 grid gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="certification-public">
                    <flux:heading>{{ __('certification.box.title') }}</flux:heading>
                    @if ($decided->status === \App\Enums\CertificationStatus::Granted)
                        <div><flux:badge size="sm" color="green" icon="check-badge">{{ __('certification.badge') }}</flux:badge></div>
                        <flux:text>{{ __('certification.box.granted_on', ['date' => $decided->decided_at->locale(app()->getLocale())->isoFormat('LL')]) }}</flux:text>
                    @else
                        <flux:text class="text-red-700 dark:text-red-400" data-test="certification-revoked">{{ __('certification.box.revoked_on', ['date' => $decided->revoked_at?->locale(app()->getLocale())->isoFormat('LL')]) }}</flux:text>
                    @endif
                    @if ($decided->reports->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach ($decided->reports->sortBy(fn ($r) => $r->locale === 'en') as $report)
                                <flux:button size="xs" icon="document-arrow-down" :href="route('modules.report', ['author' => $module->author->handle, 'module' => $module->name, 'number' => $version->version, 'locale' => $report->locale])" target="_blank" data-test="report-{{ $report->locale }}">
                                    {{ __('certification.box.report', ['language' => \App\Enums\Locale::from($report->locale)->nativeName()]) }}
                                </flux:button>
                            @endforeach
                        </div>
                    @elseif ($decided->status === \App\Enums\CertificationStatus::Granted)
                        <flux:text class="text-sm">{{ __('certification.box.report_pending') }}</flux:text>
                    @endif
                </div>
            @endif

            {{-- Certification (W06) : pour l'auteur d'un module public, demande sur une version publiée. --}}
            @if ($isAuthor && $version->status === \App\Enums\VersionStatus::Published && app(\App\Services\Certification\CertificationService::class)->canRequest($module, auth()->user()))
                @php($openCase = \App\Models\Certification::query()->where('module_id', $module->id)->whereIn('status', \App\Enums\CertificationStatus::open())->first())
                <div class="mt-4 grid gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="certification-box">
                    <flux:heading>{{ __('certification.box.title') }}</flux:heading>
                    @if ($openCase)
                        <flux:text>{{ __('certification.box.open', ['version' => $openCase->version->version, 'status' => $openCase->status->label()]) }}</flux:text>
                        <div><flux:button size="sm" :href="route('certifications.show', $openCase)" wire:navigate>{{ __('certification.mine.open') }}</flux:button></div>
                    @else
                        <flux:text>{{ __('certification.box.text') }}</flux:text>
                        <div><flux:button size="sm" variant="primary" :href="route('modules.certification', ['author' => $module->author->handle, 'module' => $module->name, 'number' => $version->version])" wire:navigate data-test="request-certification-link">{{ __('certification.request.title') }}</flux:button></div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
