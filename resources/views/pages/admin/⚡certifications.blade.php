<?php

use App\Enums\CertificationStatus;
use App\Models\Certification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-63 Certifications (F-MOD-03) : dossiers par statut, les plus anciens d'abord ; « À traiter »
 * regroupe demandées, en audit et en complément.
 */
new #[Layout('layouts::admin')] #[Title('certification.admin.title')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'open';

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function certifications()
    {
        $statuses = match ($this->tab) {
            'granted' => [CertificationStatus::Granted],
            'refused' => [CertificationStatus::Refused],
            'revoked' => [CertificationStatus::Revoked],
            default => CertificationStatus::open(),
        };

        return Certification::query()->whereIn('status', $statuses)
            ->with(['module.author', 'version', 'assignee'])
            ->oldest('requested_at')
            ->paginate(30);
    }

    #[Computed]
    public function openCount(): int
    {
        return Certification::query()->whereIn('status', CertificationStatus::open())->count();
    }
}; ?>

<div class="grid gap-5">
    <flux:heading size="xl" level="1">{{ __('certification.admin.title') }}</flux:heading>

    <div class="flex flex-wrap gap-2" role="tablist">
        @foreach ([['open', __('certification.admin.tab_open', ['count' => $this->openCount])], ['granted', __('certification.status.granted')], ['refused', __('certification.status.refused')], ['revoked', __('certification.status.revoked')]] as [$key, $label])
            <flux:button size="sm" :variant="$tab === $key ? 'primary' : 'outline'" wire:click="$set('tab', '{{ $key }}')" data-test="tab-{{ $key }}">{{ $label }}</flux:button>
        @endforeach
    </div>

    @if ($this->certifications->isEmpty())
        <x-empty-state icon="check-badge" :title="__('certification.admin.empty')" data-test="admin-certifications-empty" />
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm" data-test="admin-certifications">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.module') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.version') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.status') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.admin.requested') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.admin.assignee') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->certifications as $certification)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="{{ $certification->id }}">
                            <td class="whitespace-nowrap px-3 py-2 font-mono font-semibold" dir="ltr">
                                <a href="{{ route('admin.certifications.show', $certification) }}" wire:navigate>{{ $certification->module->fullName() }}</a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono" dir="ltr">{{ $certification->version->version }}</td>
                            <td class="px-3 py-2"><x-status-badge :status="$certification->status" /></td>
                            <td class="whitespace-nowrap px-3 py-2"><x-relative-date :at="$certification->requested_at" /></td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $certification->assignee?->displayName() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $this->certifications->links() }}</div>
    @endif
</div>
