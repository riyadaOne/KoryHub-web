<?php

use App\Enums\ActorType;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\AuditPresenter;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-71 Journal d'audit (F-ADM-04 à F-ADM-06), lecture seule : aucun bouton d'écriture, la base
 * refuse toute modification (déclencheurs, TRD §21). Filtres période, auteur (@identifiant ou
 * « system »), action, type d'objet ; les plus récents d'abord, 50 par page.
 */
new #[Layout('layouts::admin')] #[Title('admin.audit.title')] class extends Component {
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $actor = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $subject = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function events()
    {
        $handle = ltrim(trim($this->actor), '@');

        return AuditEvent::query()
            ->with(['actor', 'changes'])
            ->when($this->validDate($this->from), fn ($q, $date) => $q->where('occurred_at', '>=', $date->startOfDay()))
            ->when($this->validDate($this->to), fn ($q, $date) => $q->where('occurred_at', '<=', $date->endOfDay()))
            ->when($handle === 'system', fn ($q) => $q->where('actor_type', ActorType::System))
            ->when($handle !== '' && $handle !== 'system', fn ($q) => $q->whereIn('actor_id', User::query()->select('id')->where('handle', $handle)))
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->when(isset(AuditPresenter::SUBJECTS[$this->subject]), fn ($q) => $q->where('subject_type', $this->subject))
            ->latest('occurred_at')
            ->paginate(50);
    }

    /** @return list<string> */
    #[Computed]
    public function actions(): array
    {
        return AuditEvent::query()->distinct()->orderBy('action')->pluck('action')->all();
    }

    private function validDate(string $value): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::parse($value) : null;
    }
}; ?>

<div class="grid gap-5">
    <div>
        <flux:heading size="xl" level="1">{{ __('admin.audit.title') }}</flux:heading>
        <flux:text class="mt-1">{{ __('admin.audit.read_only') }}</flux:text>
    </div>

    <div class="flex flex-wrap items-end gap-3" data-test="audit-filters">
        <flux:input type="date" wire:model.live="from" :label="__('admin.audit.from')" class="max-w-44" />
        <flux:input type="date" wire:model.live="to" :label="__('admin.audit.to')" class="max-w-44" />
        <flux:input wire:model.live.debounce.400ms="actor" :label="__('admin.audit.actor')" :placeholder="__('admin.audit.actor_hint')" class="max-w-52" data-test="filter-actor" />
        <flux:select wire:model.live="action" :label="__('audit.action')" class="max-w-60" data-test="filter-action">
            <flux:select.option value="">{{ __('admin.audit.all') }}</flux:select.option>
            @foreach ($this->actions as $item)
                <flux:select.option :value="$item">{{ \App\Support\AuditPresenter::action($item) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="subject" :label="__('audit.subject')" class="max-w-52" data-test="filter-subject">
            <flux:select.option value="">{{ __('admin.audit.all') }}</flux:select.option>
            @foreach (\App\Support\AuditPresenter::SUBJECTS as $class => $key)
                <flux:select.option :value="$class">{{ \App\Support\AuditPresenter::subjectType($class) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->events->isEmpty())
        <x-empty-state icon="document-magnifying-glass" :title="__('admin.audit.empty')" data-test="audit-empty" />
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm" data-test="audit-table">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('audit.when') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('admin.audit.actor') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('audit.action') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('audit.subject') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('admin.audit.reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->events as $event)
                        <tr class="border-t border-zinc-200 align-top dark:border-zinc-700" wire:key="{{ $event->id }}">
                            <td class="whitespace-nowrap px-3 py-2"><a href="{{ route('admin.audit.show', $event->id) }}" wire:navigate>{{ $event->occurred_at->format('d/m/Y H:i') }}</a></td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono" dir="ltr">{{ $event->actor ? '@'.$event->actor->handle : __('admin.audit.system') }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ \App\Support\AuditPresenter::action($event->action) }}</td>
                            <td class="px-3 py-2">{{ \App\Support\AuditPresenter::subjectType($event->subject_type) }} · <span class="font-mono" dir="ltr">{{ \App\Support\AuditPresenter::subject($event) ?? '—' }}</span></td>
                            <td class="px-3 py-2">{{ \Illuminate\Support\Str::limit($event->reason ?? '—', 80) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $this->events->links() }}</div>
    @endif
</div>
