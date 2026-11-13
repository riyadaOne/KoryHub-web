<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * S-32 Mes modules (F-REG-13) : publics, privés et archivés ; dernière version ; liens vers la
 * fiche et les réglages. Liste vide : les commandes kory init et kory push.
 */
new #[Title('modules.mine.title')] class extends Component {
    #[Computed]
    public function modules()
    {
        return Auth::user()->modules()->with(['latestVersion', 'author'])->latest('updated_at')->get();
    }
}; ?>

<section class="grid gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('modules.mine.title') }}</flux:heading>
        <flux:subheading>{{ __('modules.mine.subtitle') }}</flux:subheading>
    </div>

    <x-auth-session-status :status="session('status')" />

    @if ($this->modules->isEmpty())
        <x-empty-state icon="cube" :title="__('modules.mine.empty')" :description="__('modules.mine.empty_text')">
            <x-slot:actions>
                <x-copy-command command="kory init" />
                <x-copy-command command="kory push" />
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <table class="w-full text-sm" data-test="my-modules">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3.5 py-2.5 text-start font-semibold">{{ __('modules.mine.module') }}</th>
                        <th class="px-3.5 py-2.5 text-start font-semibold">{{ __('modules.form.visibility') }}</th>
                        <th class="px-3.5 py-2.5 text-start font-semibold">{{ __('modules.mine.latest') }}</th>
                        <th class="px-3.5 py-2.5 text-start font-semibold">{{ __('modules.mine.updated') }}</th>
                        <th class="px-3.5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->modules as $module)
                        <tr @class(['border-t border-zinc-200 dark:border-zinc-700', 'text-zinc-500' => $module->status === \App\Enums\ModuleStatus::Archived]) wire:key="m-{{ $module->id }}">
                            <td class="px-3.5 py-2.5 font-mono font-semibold" dir="ltr">
                                <a href="{{ route('modules.show', ['author' => $module->author->handle, 'module' => $module->name]) }}" wire:navigate class="hover:underline">{{ $module->fullName() }}</a>
                            </td>
                            <td class="px-3.5 py-2.5">
                                <div class="flex flex-wrap gap-1.5">
                                    <x-status-badge :status="$module->visibility" />
                                    @if ($module->status === \App\Enums\ModuleStatus::Archived)
                                        <x-status-badge :status="$module->status" />
                                    @endif
                                </div>
                            </td>
                            <td class="px-3.5 py-2.5 font-mono" dir="ltr">{{ $module->latestVersion?->version ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3.5 py-2.5"><x-relative-date :at="$module->updated_at" /></td>
                            <td class="px-3.5 py-2.5 text-end">
                                <flux:button size="xs" :href="route('modules.settings', ['author' => $module->author->handle, 'module' => $module->name])" wire:navigate>{{ __('modules.settings') }}</flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
