<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-61 Signalements (F-MOD-10 à F-MOD-12) : « À traiter » (ouverts et en examen), les plus anciens
 * d'abord, ceux de plus de 48 h marqués ; nombre de signalements ouverts sur le même objet.
 */
new #[Layout('layouts::admin')] #[Title('moderation.admin.reports')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'open';

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function reports()
    {
        return Report::query()
            ->whereIn('status', $this->tab === 'closed' ? [ReportStatus::ClosedNoAction, ReportStatus::ClosedWithAction] : ReportStatus::open())
            ->with('assignee')
            ->when($this->tab === 'closed', fn ($q) => $q->latest('resolved_at'), fn ($q) => $q->oldest())
            ->paginate(30);
    }

    /** Signalements ouverts par objet : « type:id » => nombre. */
    #[Computed]
    public function sameTarget(): array
    {
        return Report::query()->whereIn('status', ReportStatus::open())
            ->select('target_type', 'target_id', DB::raw('COUNT(*) as total'))
            ->groupBy('target_type', 'target_id')->get()
            ->mapWithKeys(fn ($row) => [$row->target_type->value.':'.$row->target_id => (int) $row->total])->all();
    }

    #[Computed]
    public function openCount(): int
    {
        return Report::query()->whereIn('status', ReportStatus::open())->count();
    }

    public function isStale(Report $report): bool
    {
        return $report->status->isOpen() && $report->created_at->lte(now()->subHours((int) config('koryhub.moderation.stale_hours', 48)));
    }
}; ?>

<div class="grid gap-5">
    <flux:heading size="xl" level="1">{{ __('moderation.admin.reports') }}</flux:heading>

    <div class="flex flex-wrap gap-2" role="tablist">
        @foreach ([['open', __('moderation.admin.tab_open', ['count' => $this->openCount])], ['closed', __('moderation.admin.tab_closed')]] as [$key, $label])
            <flux:button size="sm" :variant="$tab === $key ? 'primary' : 'outline'" wire:click="$set('tab', '{{ $key }}')" data-test="tab-{{ $key }}">{{ $label }}</flux:button>
        @endforeach
    </div>

    @if ($this->reports->isEmpty())
        <x-empty-state icon="flag" :title="__('moderation.admin.empty')" data-test="admin-reports-empty" />
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm" data-test="admin-reports">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.target') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.reason') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.same_target') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.status') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.received') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.admin.assignee') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->reports as $report)
                        @php($same = $this->sameTarget[$report->target_type->value.':'.$report->target_id] ?? 0)
                        <tr @class(['border-t border-zinc-200 dark:border-zinc-700', 'shadow-[inset_3px_0_0_var(--color-red-600)]' => $this->isStale($report)]) wire:key="{{ $report->id }}" @if ($this->isStale($report)) data-test="stale" @endif>
                            <td class="px-3 py-2">
                                <a href="{{ route('admin.reports.show', $report) }}" class="font-semibold" wire:navigate>{{ $report->targetLabel() }}</a>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">· {{ $report->target_type->label() }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $report->reason->label() }}</td>
                            <td class="px-3 py-2">
                                @if ($same >= (int) config('koryhub.moderation.multiple_threshold', 3))
                                    <flux:badge size="sm" color="red" data-test="multiple">{{ $same }}</flux:badge>
                                @else
                                    {{ $same ?: '—' }}
                                @endif
                            </td>
                            <td class="px-3 py-2"><x-status-badge :status="$report->status" /></td>
                            <td class="whitespace-nowrap px-3 py-2"><x-relative-date :at="$report->created_at" /></td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $report->assignee?->displayName() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $this->reports->links() }}</div>
    @endif
</div>
