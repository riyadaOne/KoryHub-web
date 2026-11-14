<?php

use App\Models\AuditEvent;
use App\Support\AuditPresenter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Mon activité (S-47, F-ADM-07) : les actions du compte dans le journal d'audit (publications,
 * versions, réglages des modules, jetons, avis…), les plus récentes d'abord, 50 par page ;
 * visible par l'intéressé seul. Le journal de sécurité (S-40) reste à part.
 */
new #[Title('audit.mine.title')] class extends Component {
    use WithPagination;

    #[Computed]
    public function events()
    {
        return AuditEvent::query()->where('actor_id', Auth::id())->with('changes')->latest('occurred_at')->paginate(50);
    }

    public function subject(AuditEvent $event): string
    {
        return AuditPresenter::subject($event) ?? AuditPresenter::subjectType($event->subject_type);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('audit.mine.title')" :subheading="__('audit.mine.subtitle')" wide>
        @if ($this->events->isEmpty())
            <x-empty-state icon="clock" :title="__('audit.mine.empty')" data-test="activity-empty" />
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm" data-test="activity-table">
                    <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('audit.when') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('audit.action') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('audit.subject') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('audit.change') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->events as $event)
                            <tr class="border-t border-zinc-200 align-top dark:border-zinc-700" wire:key="{{ $event->id }}">
                                <td class="whitespace-nowrap px-3 py-2"><x-relative-date :at="$event->occurred_at" /></td>
                                <td class="whitespace-nowrap px-3 py-2">{{ \App\Support\AuditPresenter::action($event->action) }}</td>
                                <td class="px-3 py-2 font-mono" dir="ltr">{{ $this->subject($event) }}</td>
                                <td class="px-3 py-2"><x-audit.changes :event="$event" compact /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $this->events->links() }}</div>
        @endif
    </x-pages::settings.layout>
</section>
