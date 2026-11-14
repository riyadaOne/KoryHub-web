<?php

use App\Models\AuditEvent;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * S-71 Détail d'un événement d'audit (F-ADM-04, F-ADM-06), lecture seule : auteur, date exacte
 * (UTC), objet, motif ; chaque champ avec sa valeur avant et après (masqué pour une donnée personnelle).
 */
new #[Layout('layouts::admin')] #[Title('admin.audit.title')] class extends Component {
    #[Locked]
    public string $eventId = '';

    public function mount(string $event): void
    {
        $this->eventId = AuditEvent::query()->findOrFail($event)->id;
    }

    public function with(): array
    {
        return ['event' => AuditEvent::query()->with(['actor', 'changes'])->findOrFail($this->eventId)];
    }
}; ?>

<div class="grid gap-5">
    <div>
        <flux:link :href="route('admin.audit.index')" wire:navigate class="text-sm">← {{ __('admin.audit.title') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ \App\Support\AuditPresenter::action($event->action) }}</flux:heading>
        <flux:text class="mt-1">
            {{ $event->occurred_at->format('Y-m-d H:i:s') }} UTC
            · <span class="font-mono" dir="ltr">{{ $event->actor ? '@'.$event->actor->handle : __('admin.audit.system') }}</span>
            · {{ \App\Support\AuditPresenter::subjectType($event->subject_type) }} <span class="font-mono" dir="ltr">{{ \App\Support\AuditPresenter::subject($event) ?? $event->subject_id }}</span>
            · <span class="font-mono text-xs" dir="ltr">{{ $event->action }}</span>
        </flux:text>
        @if ($event->reason)
            <flux:text class="mt-2" data-test="audit-reason">{{ __('admin.audit.reason') }} : {{ $event->reason }}</flux:text>
        @endif
    </div>

    <x-audit.changes :event="$event" data-test="audit-changes" />
</div>
