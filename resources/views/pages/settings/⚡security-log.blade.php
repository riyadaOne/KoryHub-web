<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Journal de sécurité (S-40, F-AUT-19) : connexions et changements de sécurité du compte
 * connecté, les plus récents d'abord, 50 par page. Aucun autre compte n'y a accès.
 */
new #[Title('kit.security_log')] class extends Component {
    use WithPagination;

    #[Computed]
    public function events()
    {
        return Auth::user()->securityEvents()->latest('occurred_at')->paginate(50);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('account.security_log.title')" :subheading="__('account.security_log.subtitle')">
        @if ($this->events->isEmpty())
            <x-empty-state icon="shield-check" :title="__('account.security_log.empty')" />
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm" data-test="security-log-table">
                    <thead class="text-start text-xs text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('account.security_log.when') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('account.security_log.event') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('account.security_log.ip') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('account.security_log.browser') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->events as $event)
                            <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="{{ $event->id }}">
                                <td class="whitespace-nowrap px-3 py-2"><x-relative-date :at="$event->occurred_at" /></td>
                                <td class="whitespace-nowrap px-3 py-2"><x-status-badge :status="$event->type" /></td>
                                <td class="whitespace-nowrap px-3 py-2 font-mono">{{ $event->ip }}</td>
                                <td dir="ltr" class="max-w-40 truncate px-3 py-2 text-zinc-500 dark:text-zinc-400" title="{{ $event->user_agent }}">{{ $event->user_agent }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $this->events->links() }}</div>
        @endif
    </x-pages::settings.layout>
</section>
