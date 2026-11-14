<?php

use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Exceptions\InvalidTransition;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Services\Moderation\ModerationRefused;
use App\Services\Moderation\ModerationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * S-66 Détail d'un module public (F-RCH-12, F-MOD-10, F-MOD-13, T-13) : un module privé renvoie
 * 404. Case « Mis en avant » ; par version : avertissement de sécurité (poser, lever), retrait
 * légal (mot de passe redemandé). Motif toujours obligatoire, journal d'audit.
 */
new #[Layout('layouts::admin')] #[Title('moderation.admin.modules')] class extends Component {
    #[Locked]
    public string $moduleId = '';

    #[Locked]
    public ?string $versionId = null;

    public string $reason = '';

    public string $publicMessage = '';

    public ?string $notice = null;

    public function mount(string $module): void
    {
        $found = Module::query()->where('visibility', Visibility::Public)->findOrFail($module);
        $this->moduleId = $found->id;
    }

    #[Computed]
    public function module(): Module
    {
        return Module::query()->where('visibility', Visibility::Public)->with(['author', 'category'])->findOrFail($this->moduleId);
    }

    #[Computed]
    public function versions()
    {
        return $this->module->versions()->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated, VersionStatus::Removed])
            ->orderByDesc('major')->orderByDesc('minor')->orderByDesc('patch')->get();
    }

    public function open(string $action, string $versionId): void
    {
        $this->resetErrorBag();
        [$this->reason, $this->publicMessage, $this->versionId] = ['', '', $versionId];
        if ($action === 'remove' && ! $this->passwordRecentlyConfirmed()) {
            session()->put('url.intended', route('admin.modules.show', $this->moduleId));
            $this->redirect(route('password.confirm'));

            return;
        }
        Flux::modal($action.'-version')->show();
    }

    public function toggleFeatured(ModerationService $moderation): void
    {
        $this->run(fn () => $moderation->setFeatured($this->module, Auth::user(), ! $this->module->is_featured), null, 'moderation.admin.saved');
    }

    public function atRisk(ModerationService $moderation): void
    {
        $this->run(fn () => $moderation->markAtRisk($this->version(), Auth::user(), $this->reason), 'at-risk', 'moderation.admin.at_risk_done');
    }

    public function clearRisk(ModerationService $moderation): void
    {
        $this->run(fn () => $moderation->clearAtRisk($this->version(), Auth::user(), $this->reason), 'clear-risk', 'moderation.admin.at_risk_cleared');
    }

    public function remove(ModerationService $moderation): void
    {
        abort_unless($this->passwordRecentlyConfirmed(), 403);
        $this->run(fn () => $moderation->removeVersion($this->version(), Auth::user(), $this->publicMessage, $this->reason), 'remove', 'moderation.admin.removed_done');
    }

    private function version(): ModuleVersion
    {
        return $this->module->versions()->findOrFail((string) $this->versionId);
    }

    private function run(Closure $action, ?string $modal, string $done): void
    {
        $this->resetErrorBag();
        try {
            $action();
            $this->notice = __($done);
            if ($modal !== null) {
                Flux::modal($modal.'-version')->close();
            }
            [$this->reason, $this->publicMessage] = ['', ''];
        } catch (ModerationRefused|InvalidTransition $exception) {
            $this->addError('reason', $exception->getMessage());
        }
        unset($this->module, $this->versions);
    }

    /** Mot de passe confirmé récemment (même délai que le middleware password.confirm). */
    private function passwordRecentlyConfirmed(): bool
    {
        return time() - (int) session('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 10800);
    }
}; ?>

@php($m = $this->module)
<div class="grid gap-5">
    <div>
        <flux:heading size="xl" level="1" class="font-mono" dir="ltr">{{ $m->fullName() }}</flux:heading>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
            @if ($m->category)<span>{{ $m->category->name() }}</span>@endif
            <span>↓ {{ \Illuminate\Support\Number::format($m->download_count) }}</span>
            <flux:link :href="route('modules.show', ['author' => $m->author->handle, 'module' => $m->name])" external>{{ __('certification.admin.open_page') }}</flux:link>
        </div>
    </div>

    @if ($notice)
        <flux:callout icon="check-circle" color="green" data-test="admin-notice"><flux:callout.heading>{{ $notice }}</flux:callout.heading></flux:callout>
    @endif
    @error('reason')
        <flux:callout icon="exclamation-triangle" color="red" data-test="admin-error"><flux:callout.heading>{{ $message }}</flux:callout.heading></flux:callout>
    @enderror

    <div class="flex max-w-md items-center justify-between gap-3">
        <span>{{ __('moderation.admin.featured_home') }}</span>
        <flux:switch :checked="$m->is_featured" wire:click="toggleFeatured" :aria-label="__('moderation.admin.featured')" data-test="featured" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm" data-test="admin-versions">
            <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                <tr>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.version') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.status') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.published') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->versions as $version)
                    <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="{{ $version->id }}">
                        <td class="whitespace-nowrap px-3 py-2 font-mono" dir="ltr">{{ $version->version }}</td>
                        <td class="whitespace-nowrap px-3 py-2">
                            <x-status-badge :status="$version->status" />
                            @if ($version->at_risk_at)<flux:badge size="sm" color="red">{{ __('moderation.at_risk') }}</flux:badge>@endif
                            @if ($version->isCertified())<flux:badge size="sm" color="green">{{ __('certification.badge') }}</flux:badge>@endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2">@if ($version->published_at)<x-relative-date :at="$version->published_at" />@endif</td>
                        <td class="px-3 py-2">
                            @if ($version->status->isAvailable())
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($version->at_risk_at)
                                        <flux:button size="sm" wire:click="open('clear-risk', '{{ $version->id }}')" data-test="clear-risk-{{ $version->version }}">{{ __('moderation.admin.clear_risk') }}</flux:button>
                                    @else
                                        <flux:button size="sm" wire:click="open('at-risk', '{{ $version->id }}')" data-test="at-risk-{{ $version->version }}">{{ __('moderation.admin.at_risk') }}</flux:button>
                                    @endif
                                    <flux:button size="sm" variant="danger" wire:click="open('remove', '{{ $version->id }}')" data-test="remove-{{ $version->version }}">{{ __('moderation.admin.remove') }}</flux:button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @foreach ([['at-risk', __('moderation.admin.at_risk'), 'atRisk', __('moderation.admin.at_risk_message')], ['clear-risk', __('moderation.admin.clear_risk'), 'clearRisk', __('certification.admin.reason')]] as [$action, $label, $method, $field])
        <x-confirm-step :name="$action.'-version'" :heading="$label">
            <flux:textarea wire:model="reason" :label="$field" rows="3" data-test="reason-{{ $action }}" />
            <x-slot:action><flux:button variant="primary" wire:click="{{ $method }}" data-test="confirm-{{ $action }}">{{ $label }}</flux:button></x-slot:action>
        </x-confirm-step>
    @endforeach

    <x-confirm-step name="remove-version" :heading="__('moderation.admin.remove')">
        {{ __('moderation.admin.remove_text') }}
        <flux:textarea wire:model="reason" :label="__('certification.admin.reason')" rows="3" class="mt-3" data-test="reason-remove" />
        <flux:input wire:model="publicMessage" :label="__('moderation.admin.public_message')" class="mt-3" data-test="public-message" />
        <x-slot:action><flux:button variant="danger" wire:click="remove" data-test="confirm-remove">{{ __('moderation.admin.remove') }}</flux:button></x-slot:action>
    </x-confirm-step>
</div>
