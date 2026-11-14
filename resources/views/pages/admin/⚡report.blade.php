<?php

use App\Enums\AccountStatus;
use App\Enums\Visibility;
use App\Exceptions\InvalidTransition;
use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
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
 * S-62 Détail d'un signalement (F-MOD-10, F-MOD-13, F-MOD-15, T-13), modèle « Dossier » : à gauche
 * la cible ; à droite les signalements ouverts sur le même objet (avec leur auteur, visible des
 * admins seulement) et la décision. Un module devenu privé n'est jamais montré (décision 1a).
 * Le retrait légal redemande le mot de passe ; un motif est toujours obligatoire.
 */
new #[Layout('layouts::admin')] #[Title('moderation.admin.reports')] class extends Component {
    #[Locked]
    public string $reportId = '';

    public string $reason = '';

    public string $publicMessage = '';

    public ?string $notice = null;

    public function mount(Report $report): void
    {
        $this->reportId = $report->id;
    }

    #[Computed]
    public function report(): Report
    {
        return Report::query()->with(['assignee'])->findOrFail($this->reportId);
    }

    #[Computed]
    public function target(): Module|ModuleVersion|User|Review|null
    {
        return $this->report->target();
    }

    /** Module concerné, ou null : compte signalé, ou module devenu privé (jamais montré, T-13). */
    #[Computed]
    public function module(): ?Module
    {
        $module = $this->report->targetModule();

        return $module?->visibility === Visibility::Public && ! $module->trashed() ? $module : null;
    }

    #[Computed]
    public function isPrivate(): bool
    {
        return $this->report->targetModule()?->visibility === Visibility::Private;
    }

    /** Version visée par les actions : la version signalée, ou la dernière version stable du module. */
    #[Computed]
    public function version(): ?ModuleVersion
    {
        if ($this->module === null) {
            return null;
        }

        return $this->target instanceof ModuleVersion ? $this->target : ($this->target instanceof Module ? $this->target->latestVersion : null);
    }

    /** Compte responsable de la cible, s'il peut être suspendu ou supprimé (ni admin ni supprimé). */
    #[Computed]
    public function owner(): ?User
    {
        $owner = $this->report->targetOwner();

        return $owner !== null && ! $owner->isAdmin() && $owner->status !== AccountStatus::Deleted ? $owner : null;
    }

    #[Computed]
    public function related()
    {
        return Report::query()->where('target_type', $this->report->target_type)->where('target_id', $this->report->target_id)
            ->with('reporter')->latest()->get();
    }

    public function open(string $action): void
    {
        $this->resetErrorBag();
        [$this->reason, $this->publicMessage] = ['', ''];
        if (in_array($action, ['remove', 'suspend', 'delete'], true) && ! $this->passwordRecentlyConfirmed()) {
            session()->put('url.intended', route('admin.reports.show', $this->reportId));
            $this->redirect(route('password.confirm'));

            return;
        }
        Flux::modal($action.'-report')->show();
    }

    public function take(ModerationService $moderation): void
    {
        $this->run(fn () => $moderation->take($this->report, Auth::user()), null, 'moderation.admin.taken');
    }

    public function close(ModerationService $moderation): void
    {
        $this->run(fn () => $moderation->closeNoAction($this->report, Auth::user(), $this->reason), 'close', 'moderation.admin.closed');
    }

    public function atRisk(ModerationService $moderation): void
    {
        abort_if($this->version === null, 404);
        $this->run(fn () => $moderation->markAtRisk($this->version, Auth::user(), $this->reason, $this->report), 'at-risk', 'moderation.admin.at_risk_done');
    }

    public function remove(ModerationService $moderation): void
    {
        abort_unless($this->passwordRecentlyConfirmed(), 403);
        abort_if($this->version === null, 404);
        $this->run(fn () => $moderation->removeVersion($this->version, Auth::user(), $this->publicMessage, $this->reason, $this->report), 'remove', 'moderation.admin.removed_done');
    }

    public function hide(ModerationService $moderation): void
    {
        abort_unless($this->target instanceof Review && $this->module !== null, 404);
        $this->run(fn () => $moderation->hideReview($this->target, Auth::user(), $this->reason, $this->report), 'hide', 'moderation.admin.hidden_done');
    }

    public function suspend(ModerationService $moderation): void
    {
        abort_unless($this->passwordRecentlyConfirmed(), 403);
        abort_if($this->owner === null, 404);
        $this->run(fn () => $moderation->suspendAccount($this->owner, Auth::user(), $this->reason, $this->report), 'suspend', 'moderation.admin.suspended_done');
    }

    public function deleteAccount(ModerationService $moderation): void
    {
        abort_unless($this->passwordRecentlyConfirmed(), 403);
        abort_if($this->owner === null, 404);
        $this->run(fn () => $moderation->deleteAccount($this->owner, Auth::user(), $this->reason, $this->report), 'delete', 'moderation.admin.deleted_done');
    }

    private function run(Closure $action, ?string $modal, string $done): void
    {
        $this->resetErrorBag();
        try {
            $action();
            $this->notice = __($done);
            if ($modal !== null) {
                Flux::modal($modal.'-report')->close();
            }
            [$this->reason, $this->publicMessage] = ['', ''];
        } catch (ModerationRefused|InvalidTransition $exception) {
            $this->addError('reason', $exception->getMessage());
        }
        unset($this->report, $this->target, $this->module, $this->version, $this->related, $this->owner);
    }

    /** Mot de passe confirmé récemment (même délai que le middleware password.confirm). */
    private function passwordRecentlyConfirmed(): bool
    {
        return time() - (int) session('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 10800);
    }
}; ?>

@php($r = $this->report)
@php($target = $this->target)
<div class="grid gap-5">
    <div>
        <flux:heading size="xl" level="1">{{ $this->isPrivate ? $r->target_type->label() : $r->targetLabel() }}</flux:heading>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
            <x-status-badge :status="$r->status" data-test="report-status" />
            <span>{{ $r->target_type->label() }} · {{ $r->reason->label() }}</span>
            <span>{{ __('certification.admin.assignee') }} : {{ $r->assignee?->displayName() ?? '—' }}</span>
            @if ($r->resolution)
                <span>{{ $r->resolution->label() }}</span>
            @endif
        </div>
    </div>

    @if ($notice)
        <flux:callout icon="check-circle" color="green" data-test="admin-notice"><flux:callout.heading>{{ $notice }}</flux:callout.heading></flux:callout>
    @endif
    @error('reason')
        <flux:callout icon="exclamation-triangle" color="red" data-test="admin-error"><flux:callout.heading>{{ $message }}</flux:callout.heading></flux:callout>
    @enderror

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="grid min-w-0 content-start gap-3">
            @if ($this->isPrivate)
                <x-empty-state icon="lock-closed" :title="__('moderation.admin.private')" :description="__('moderation.admin.private_text')" data-test="private-target" />
            @elseif ($target === null)
                <x-empty-state icon="trash" :title="__('moderation.target_gone')" />
            @elseif ($target instanceof \App\Models\User)
                <div class="grid gap-1 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <span class="font-mono font-semibold" dir="ltr">{{ '@'.$target->handle }}</span>
                    @if ($target->name)<span>{{ $target->name }}</span>@endif
                    <x-status-badge :status="$target->status" class="justify-self-start" />
                    <flux:link :href="route('profiles.show', $target->handle)" external>{{ __('certification.admin.open_page') }}</flux:link>
                </div>
            @elseif ($target instanceof \App\Models\Review)
                <div class="grid gap-1.5 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="review-target">
                    <div class="flex flex-wrap items-center gap-2 text-sm"><span class="font-mono font-semibold" dir="ltr">{{ '@'.$target->user->handle }}</span><x-rating :value="$target->rating" />@if ($target->hidden_at)<flux:badge size="sm" color="zinc">{{ __('moderation.admin.hidden') }}</flux:badge>@endif</div>
                    <p class="m-0 whitespace-pre-line text-sm">{{ $target->comment }}</p>
                </div>
            @elseif ($this->version)
                @php($v = $this->version)
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono" dir="ltr">{{ $v->version }}</span>
                    <x-status-badge :status="$v->status" />
                    @if ($v->at_risk_at)<flux:badge size="sm" color="red">{{ __('moderation.at_risk') }}</flux:badge>@endif
                    <flux:link :href="route('modules.version', ['author' => $v->module->author->handle, 'module' => $v->module->name, 'number' => $v->version])" external>{{ __('certification.admin.open_page') }}</flux:link>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <ul class="m-0 grid list-none gap-1 p-0 font-mono text-sm" dir="ltr" data-test="target-files">
                        @foreach ($v->files()->orderBy('path')->get() as $file)
                            <li>
                                @if ($v->archive_path)
                                    <a href="{{ route('modules.files', ['author' => $v->module->author->handle, 'module' => $v->module->name, 'path' => $file->path, 'v' => $v->version]) }}" target="_blank" rel="noopener">{{ $file->path }}</a>
                                @else
                                    {{ $file->path }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <aside class="grid content-start gap-3">
            <div class="grid gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="related-reports">
                <flux:heading>{{ trans_choice('moderation.admin.on_target', $this->related->count(), ['count' => $this->related->count()]) }}</flux:heading>
                @foreach ($this->related as $item)
                    <div class="grid gap-0.5 text-sm" wire:key="rel-{{ $item->id }}">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->reason->label() }} · {{ '@'.$item->reporter->handle }} · <x-relative-date :at="$item->created_at" /> · {{ $item->status->label() }}</span>
                        @if ($item->details)<p class="m-0 whitespace-pre-line">{{ $item->details }}</p>@endif
                    </div>
                @endforeach
            </div>

            @if ($r->status->isOpen())
                <div class="grid gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ __('moderation.admin.decision') }}</flux:heading>
                    <div class="flex flex-wrap gap-2" data-test="decisions">
                        @if ($r->status === \App\Enums\ReportStatus::Open)
                            <flux:button variant="primary" wire:click="take" data-test="take">{{ __('certification.admin.take') }}</flux:button>
                        @endif
                        <flux:button wire:click="open('close')" data-test="close">{{ __('moderation.admin.close') }}</flux:button>
                        @if ($this->version?->status->isAvailable())
                            @unless ($this->version->at_risk_at)
                                <flux:button wire:click="open('at-risk')" data-test="at-risk">{{ __('moderation.admin.at_risk') }}</flux:button>
                            @endunless
                            <flux:button variant="danger" wire:click="open('remove')" data-test="remove">{{ __('moderation.admin.remove') }}</flux:button>
                        @endif
                        @if ($target instanceof \App\Models\Review && $this->module && ! $target->hidden_at)
                            <flux:button variant="danger" wire:click="open('hide')" data-test="hide">{{ __('moderation.admin.hide') }}</flux:button>
                        @endif
                        @if ($this->owner?->status === \App\Enums\AccountStatus::Active)
                            <flux:button variant="danger" wire:click="open('suspend')" data-test="suspend">{{ __('moderation.admin.suspend_owner', ['handle' => '@'.$this->owner->handle]) }}</flux:button>
                        @endif
                        @if ($this->owner)
                            <flux:button variant="danger" wire:click="open('delete')" data-test="delete-account">{{ __('moderation.admin.delete_owner', ['handle' => '@'.$this->owner->handle]) }}</flux:button>
                        @endif
                    </div>
                    <flux:text>{{ __('moderation.admin.closes_all') }}</flux:text>
                </div>
            @endif
        </aside>
    </div>

    @foreach ([['close', __('moderation.admin.close'), 'primary', 'close'], ['at-risk', __('moderation.admin.at_risk'), 'danger', 'atRisk'], ['hide', __('moderation.admin.hide'), 'danger', 'hide'], ['suspend', __('moderation.admin.suspend'), 'danger', 'suspend'], ['delete', __('moderation.admin.delete'), 'danger', 'deleteAccount']] as [$action, $label, $variant, $method])
        <x-confirm-step :name="$action.'-report'" :heading="$label">
            <flux:textarea wire:model="reason" :label="$action === 'at-risk' ? __('moderation.admin.at_risk_message') : __('certification.admin.reason')" rows="3" data-test="reason-{{ $action }}" />
            <x-slot:action><flux:button :variant="$variant" wire:click="{{ $method }}" data-test="confirm-{{ $action }}">{{ $label }}</flux:button></x-slot:action>
        </x-confirm-step>
    @endforeach

    <x-confirm-step name="remove-report" :heading="__('moderation.admin.remove_title', ['version' => $this->version?->version])">
        {{ __('moderation.admin.remove_text') }}
        <flux:textarea wire:model="reason" :label="__('certification.admin.reason')" rows="3" class="mt-3" data-test="reason-remove" />
        <flux:input wire:model="publicMessage" :label="__('moderation.admin.public_message')" class="mt-3" data-test="public-message" />
        <x-slot:action><flux:button variant="danger" wire:click="remove" data-test="confirm-remove">{{ __('moderation.admin.remove') }}</flux:button></x-slot:action>
    </x-confirm-step>
</div>
