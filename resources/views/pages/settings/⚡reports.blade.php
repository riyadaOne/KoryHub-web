<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Mes signalements (S-48, F-MOD-09) : visibles par leur auteur seul ; objet, motif, statut et
 * résultat générique (décision W07 4a) : « Sans suite » ou « Une mesure a été prise », jamais le
 * détail de la sanction.
 */
new #[Title('moderation.mine.title')] class extends Component {
    use WithPagination;

    #[Computed]
    public function reports()
    {
        return Report::query()->where('reporter_id', Auth::id())->latest()->paginate(50);
    }

    /** Statut affiché : statut tant que le signalement est ouvert, résultat générique ensuite. */
    public function outcome(Report $report): string
    {
        return match ($report->status) {
            ReportStatus::ClosedNoAction => __('moderation.mine.no_action'),
            ReportStatus::ClosedWithAction => __('moderation.mine.with_action'),
            default => $report->status->label(),
        };
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('moderation.mine.title')" :subheading="__('moderation.mine.subtitle')" wide>
        @if ($this->reports->isEmpty())
            <x-empty-state icon="flag" :title="__('moderation.mine.empty')" data-test="reports-empty" />
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm" data-test="reports-table">
                    <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.target') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.reason') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.status') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.sent') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->reports as $report)
                            <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="{{ $report->id }}">
                                <td class="px-3 py-2">{{ $report->targetLabel() }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ $report->reason->label() }}</td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($report->status->isOpen())
                                        <x-status-badge :status="$report->status" />
                                    @else
                                        <flux:badge size="sm" color="zinc" data-test="outcome">{{ $this->outcome($report) }}</flux:badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2"><x-relative-date :at="$report->created_at" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $this->reports->links() }}</div>
        @endif
    </x-pages::settings.layout>
</section>
