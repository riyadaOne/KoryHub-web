<?php

use App\Support\SemVer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Mes téléchargements (S-46, F-RCH-14) : versions téléchargées en étant connecté, visibles par
 * l'intéressé seul ; les plus récentes d'abord, 50 par page. Badge si une version plus récente
 * existe ou si la version est dépréciée.
 */
new #[Title('downloads.title')] class extends Component {
    use WithPagination;

    #[Computed]
    public function downloads()
    {
        return Auth::user()->moduleDownloads()
            ->with(['module.author', 'module.latestVersion', 'version'])
            ->latest('last_at')
            ->paginate(50);
    }

    /** Version stable plus récente que celle téléchargée, ou null. */
    public function newer($download): ?string
    {
        $latest = $download->module->latestVersion?->version;
        $installed = SemVer::parse($download->version->version);
        $candidate = $latest !== null ? SemVer::parse($latest) : null;

        return $candidate !== null && $installed !== null && $candidate->greaterThan($installed) ? $latest : null;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('downloads.title')" :subheading="__('downloads.subtitle')" wide>
        @if ($this->downloads->isEmpty())
            <x-empty-state icon="arrow-down-tray" :title="__('downloads.empty')" :description="__('downloads.empty_text')" data-test="downloads-empty">
                <x-slot:actions><x-copy-command command="kory login" class="w-full max-w-sm" /></x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm" data-test="downloads-table">
                    <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('downloads.module') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('downloads.version') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('downloads.count') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('downloads.last') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->downloads as $download)
                            @php($newer = $this->newer($download))
                            <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="{{ $download->id }}">
                                <td class="whitespace-nowrap px-3 py-2 font-mono" dir="ltr">
                                    <a href="{{ route('modules.show', ['author' => $download->module->author->handle, 'module' => $download->module->name]) }}" class="font-semibold" wire:navigate>{{ $download->module->fullName() }}</a>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span class="font-mono" dir="ltr">{{ $download->version->version }}</span>
                                    @if ($download->version->status === \App\Enums\VersionStatus::Deprecated)
                                        <x-status-badge :status="$download->version->status" class="ms-1" />
                                    @elseif ($newer)
                                        <flux:badge size="sm" color="blue" class="ms-1" data-test="newer">{{ __('downloads.newer', ['version' => $newer]) }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $download->count }}</td>
                                <td class="whitespace-nowrap px-3 py-2"><x-relative-date :at="$download->last_at" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $this->downloads->links() }}</div>
        @endif
    </x-pages::settings.layout>
</section>
