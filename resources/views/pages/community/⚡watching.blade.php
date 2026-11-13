<?php

use App\Enums\Visibility;
use App\Enums\WatchLevel;
use App\Models\Watch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-45 Modules suivis (F-RCH-09) : niveau de chaque abonnement, modifiable dans la ligne ;
 * « Ignorer » reste listé (choix actif : plus aucune notification pour ce module).
 */
new #[Title('community.watch.title')] class extends Component {
    use WithPagination;

    #[Computed]
    public function watches()
    {
        return Auth::user()->watches()
            ->whereHas('module', fn ($q) => $q->where('visibility', Visibility::Public))
            ->with('module.author', 'module.latestVersion')
            ->latest()
            ->paginate(50);
    }

    public function setLevel(string $id, string $level): void
    {
        $watch = Watch::query()->where('user_id', Auth::id())->whereKey($id)->firstOrFail();
        if ($level === 'none') {
            $watch->delete();
        } else {
            $watch->update(['level' => WatchLevel::from($level)]);
        }
        unset($this->watches);
    }
}; ?>

<section class="grid gap-6 py-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('community.watch.title') }}</flux:heading>
        <flux:subheading>{{ __('community.watch.subtitle') }}</flux:subheading>
    </div>

    @if ($this->watches->isEmpty())
        <x-empty-state icon="eye" :title="__('community.watch.empty')" :description="__('community.watch.empty_text')" data-test="watching-empty" />
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm" data-test="watching-table">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('community.watch.module') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('community.watch.latest') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('community.watch.level') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->watches as $watch)
                        <tr @class(['border-t border-zinc-200 dark:border-zinc-700', 'text-zinc-500 dark:text-zinc-400' => $watch->level === \App\Enums\WatchLevel::Ignore]) wire:key="watch-{{ $watch->id }}">
                            <td class="whitespace-nowrap px-3 py-2 font-mono font-semibold" dir="ltr">
                                <a href="{{ route('modules.show', ['author' => $watch->module->author->handle, 'module' => $watch->module->name]) }}" wire:navigate>{{ $watch->module->fullName() }}</a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono" dir="ltr">{{ $watch->module->latestVersion?->version ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <select wire:change="setLevel('{{ $watch->id }}', $event.target.value)" class="max-w-56 rounded-md border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900" aria-label="{{ __('community.watch.level') }}" data-test="level-{{ $watch->module->name }}">
                                    @foreach (\App\Enums\WatchLevel::cases() as $level)
                                        <option value="{{ $level->value }}" @selected($watch->level === $level)>{{ $level->label() }}</option>
                                    @endforeach
                                    <option value="none">{{ __('community.watch.stop') }}</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $this->watches->links() }}</div>
    @endif
</section>
