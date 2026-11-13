<?php

use App\Exceptions\InvalidTransition;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Services\Modules\ModuleFinder;
use App\Services\Modules\ModuleLifecycle;
use App\Services\Modules\ModuleRuleViolation;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * S-04 Versions d'un kory (F-REG-15, F-REG-17, F-REG-20) : statut, date, empreinte ; pour l'auteur,
 * déprécier (message obligatoire), annuler la dépréciation, supprimer dans les 72 h. Les versions
 * rejetées ne sont visibles que par l'auteur. Confirmations dans l'écran (UXUI U4).
 */
new class extends Component {
    #[Locked]
    public string $author = '';

    #[Locked]
    public string $moduleName = '';

    #[Locked]
    public ?string $selectedId = null;

    public string $message = '';

    public ?string $notice = null;

    public bool $noticeIsError = false;

    public function mount(string $author, string $module): void
    {
        $this->author = $author;
        $this->moduleName = $module;
        $this->module;
    }

    #[Computed]
    public function module(): Module
    {
        return app(ModuleFinder::class)->find($this->author, $this->moduleName, Auth::user());
    }

    #[Computed]
    public function isAuthor(): bool
    {
        return app(ModuleFinder::class)->isAuthor($this->module, Auth::user());
    }

    #[Computed]
    public function versions()
    {
        return app(ModuleFinder::class)->versions($this->module, Auth::user());
    }

    #[Computed]
    public function selected(): ?ModuleVersion
    {
        return $this->selectedId === null ? null : $this->versions->firstWhere('id', $this->selectedId);
    }

    public function ask(string $action, string $id): void
    {
        abort_unless($this->isAuthor, 403);
        $this->selectedId = $this->versions->firstWhere('id', $id)?->id ?? abort(404);
        $this->message = '';
        Flux::modal($action.'-version')->show();
    }

    public function deprecate(ModuleLifecycle $lifecycle): void
    {
        $this->validate(['message' => ['required', 'string', 'max:255']], attributes: ['message' => __('modules.versions.message')]);
        $this->run(fn (ModuleVersion $v) => $lifecycle->deprecate($v, $this->message, Auth::user()), 'deprecate', 'modules.versions.deprecated');
    }

    public function undeprecate(ModuleLifecycle $lifecycle): void
    {
        $this->run(fn (ModuleVersion $v) => $lifecycle->undeprecate($v, Auth::user()), 'undeprecate', 'modules.versions.undeprecated');
    }

    public function delete(ModuleLifecycle $lifecycle): void
    {
        $this->run(fn (ModuleVersion $v) => $lifecycle->deleteVersion($v, Auth::user()), 'delete', 'modules.versions.deleted');
    }

    public function canDelete(ModuleVersion $version): bool
    {
        return app(ModuleLifecycle::class)->canDeleteVersion($version);
    }

    private function run(Closure $action, string $modal, string $done): void
    {
        abort_unless($this->isAuthor && $this->selected !== null, 403);

        try {
            $action($this->selected);
            [$this->notice, $this->noticeIsError] = [__($done), false];
        } catch (InvalidTransition|ModuleRuleViolation $exception) {
            [$this->notice, $this->noticeIsError] = [$exception->getMessage(), true];
        }

        $this->selectedId = null;
        unset($this->versions, $this->module);
        Flux::modal($modal.'-version')->close();
    }
}; ?>

<div>
    <x-module.header :module="$this->module" tab="versions" :version="$this->module->latestVersion" :is-author="$this->isAuthor" :files-count="$this->module->latestVersion?->files_count" :versions-count="$this->versions->count()" />

    <div class="grid gap-3 py-6">
        @if ($notice)
            <flux:callout :variant="$noticeIsError ? 'danger' : 'success'" :icon="$noticeIsError ? 'x-circle' : 'check-circle'" data-test="versions-notice">
                <flux:callout.heading>{{ $notice }}</flux:callout.heading>
            </flux:callout>
        @endif

        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <table class="w-full text-sm" data-test="versions-table">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3.5 py-2.5 text-start font-semibold">{{ __('modules.versions.version') }}</th>
                        <th class="px-3.5 py-2.5 text-start font-semibold">{{ __('modules.versions.status') }}</th>
                        <th class="px-3.5 py-2.5 text-start font-semibold">{{ __('modules.versions.published') }}</th>
                        <th class="px-3.5 py-2.5 text-start font-semibold">{{ __('modules.side.checksum') }}</th>
                        @if ($this->isAuthor)
                            <th class="px-3.5 py-2.5"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->versions as $v)
                        <tr class="border-t border-zinc-200 align-top dark:border-zinc-700" wire:key="v-{{ $v->id }}">
                            <td class="px-3.5 py-2.5 font-mono" dir="ltr">
                                <a href="{{ route('modules.version', ['author' => $this->module->author->handle, 'module' => $this->module->name, 'number' => $v->version]) }}" wire:navigate class="hover:underline">{{ $v->version }}</a>
                            </td>
                            <td class="px-3.5 py-2.5">
                                <div class="flex flex-wrap gap-1.5">
                                    <x-status-badge :status="$v->status" />
                                    @if ($v->isPrerelease())
                                        <flux:badge size="sm" color="zinc">{{ __('modules.versions.prerelease') }}</flux:badge>
                                    @endif
                                </div>
                                @if ($v->deprecation_message)
                                    <div class="mt-1 text-[13px] text-zinc-500 dark:text-zinc-400">{{ $v->deprecation_message }}</div>
                                @elseif ($v->rejection_reason)
                                    <div class="mt-1 text-[13px] text-zinc-500 dark:text-zinc-400">{{ $v->rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3.5 py-2.5"><x-relative-date :at="$v->published_at ?? $v->submitted_at" /></td>
                            <td class="whitespace-nowrap px-3.5 py-2.5 font-mono text-[13px] text-zinc-500" dir="ltr">{{ $v->archive_path ? substr($v->archive_sha256, 0, 6).'…'.substr($v->archive_sha256, -4) : '—' }}</td>
                            @if ($this->isAuthor)
                                <td class="px-3.5 py-2.5">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        @if ($v->status === \App\Enums\VersionStatus::Published)
                                            <flux:button size="xs" wire:click="ask('deprecate', '{{ $v->id }}')" data-test="deprecate-{{ $v->version }}">{{ __('modules.versions.deprecate') }}</flux:button>
                                        @elseif ($v->status === \App\Enums\VersionStatus::Deprecated)
                                            <flux:button size="xs" wire:click="ask('undeprecate', '{{ $v->id }}')" data-test="undeprecate-{{ $v->version }}">{{ __('modules.versions.undeprecate') }}</flux:button>
                                        @endif
                                        @if ($this->canDelete($v))
                                            <flux:button size="xs" variant="danger" wire:click="ask('delete', '{{ $v->id }}')" data-test="delete-{{ $v->version }}">{{ __('modules.versions.delete') }}</flux:button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="m-0 text-[13px] text-zinc-500 dark:text-zinc-400">{{ __('modules.versions.immutable') }}</p>
    </div>

    @if ($this->isAuthor)
        <x-confirm-step name="deprecate-version" :heading="__('modules.versions.deprecate_title', ['version' => $this->selected?->version ?? ''])">
            {{ __('modules.versions.deprecate_text') }}
            <flux:textarea wire:model="message" :label="__('modules.versions.message')" rows="2" class="mt-3" data-test="deprecation-message" />
            <x-slot:action>
                <flux:button variant="primary" wire:click="deprecate" data-test="confirm-deprecate">{{ __('modules.versions.deprecate') }}</flux:button>
            </x-slot:action>
        </x-confirm-step>

        <x-confirm-step name="undeprecate-version" :heading="__('modules.versions.undeprecate_title', ['version' => $this->selected?->version ?? ''])">
            <x-slot:action>
                <flux:button variant="primary" wire:click="undeprecate" data-test="confirm-undeprecate">{{ __('modules.versions.undeprecate') }}</flux:button>
            </x-slot:action>
        </x-confirm-step>

        <x-confirm-step name="delete-version" :heading="__('modules.versions.delete_title', ['version' => $this->selected?->version ?? ''])">
            {{ __('modules.versions.delete_text') }}
            <x-slot:action>
                <flux:button variant="danger" wire:click="delete" data-test="confirm-delete-version">{{ __('modules.versions.delete') }}</flux:button>
            </x-slot:action>
        </x-confirm-step>
    @endif
</div>
