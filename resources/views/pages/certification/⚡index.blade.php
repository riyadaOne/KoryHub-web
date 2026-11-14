<?php

use App\Models\Certification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-36 Mes certifications (F-MOD-01) : dossiers du développeur, les plus récents d'abord ;
 * statut, version, dernière étape ; « Répondre » pour un complément demandé.
 */
new #[Title('certification.mine.title')] class extends Component {
    use WithPagination;

    #[Computed]
    public function certifications()
    {
        return Certification::query()
            ->where('requested_by', Auth::id())
            ->with(['module.author', 'version'])
            ->latest('updated_at')
            ->paginate(30);
    }
}; ?>

<section class="grid gap-6 py-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('certification.mine.title') }}</flux:heading>
        <flux:subheading>{{ __('certification.mine.subtitle') }}</flux:subheading>
    </div>

    @if ($this->certifications->isEmpty())
        <x-empty-state icon="check-badge" :title="__('certification.mine.empty')" :description="__('certification.mine.empty_text')" data-test="certifications-empty" />
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm" data-test="certifications-table">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.module') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.version') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.status') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.last_step') }}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->certifications as $certification)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="{{ $certification->id }}">
                            <td class="whitespace-nowrap px-3 py-2 font-mono font-semibold" dir="ltr">{{ $certification->module->fullName() }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono" dir="ltr">{{ $certification->version->version }}</td>
                            <td class="px-3 py-2"><x-status-badge :status="$certification->status" /></td>
                            <td class="whitespace-nowrap px-3 py-2"><x-relative-date :at="$certification->updated_at" /></td>
                            <td class="px-3 py-2 text-end">
                                <flux:button size="xs" :variant="$certification->status === \App\Enums\CertificationStatus::ComplementRequired ? 'primary' : 'outline'" :href="route('certifications.show', $certification)" wire:navigate data-test="open-{{ $certification->module->name }}">
                                    {{ $certification->status === \App\Enums\CertificationStatus::ComplementRequired ? __('certification.mine.respond') : __('certification.mine.open') }}
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $this->certifications->links() }}</div>
    @endif
</section>
