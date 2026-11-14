<?php

use App\Enums\AccountStatus;
use App\Enums\Visibility;
use App\Exceptions\InvalidTransition;
use App\Models\PersonalAccessToken;
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
 * S-68 Détail d'un compte (F-ADM-02, F-AUT-15) : profil, modules publics, jetons du CLI (nom et
 * dates, jamais la valeur) avec révocation d'urgence ; zone de danger : suspendre ou réactiver,
 * supprimer. Suspendre et supprimer redemandent le mot de passe ; motif toujours obligatoire.
 * Jamais sur son propre compte ni sur un autre admin.
 */
new #[Layout('layouts::admin')] #[Title('moderation.admin.users')] class extends Component {
    #[Locked]
    public string $userId = '';

    #[Locked]
    public ?string $tokenId = null;

    public string $reason = '';

    public ?string $notice = null;

    public function mount(string $user): void
    {
        $this->userId = User::query()->findOrFail($user)->id;
    }

    #[Computed]
    public function user(): User
    {
        return User::query()->findOrFail($this->userId);
    }

    #[Computed]
    public function tokens()
    {
        return $this->user->tokens()->usable()->latest()->get();
    }

    #[Computed]
    public function modules()
    {
        return $this->user->modules()->where('visibility', Visibility::Public)->orderBy('name')->get();
    }

    /** Les actions de la zone de danger : ni son propre compte, ni un admin, ni un compte supprimé. */
    #[Computed]
    public function actionable(): bool
    {
        return ! $this->user->is(Auth::user()) && ! $this->user->isAdmin() && $this->user->status !== AccountStatus::Deleted;
    }

    public function open(string $action, ?string $tokenId = null): void
    {
        $this->resetErrorBag();
        [$this->reason, $this->tokenId] = ['', $tokenId];
        if (in_array($action, ['suspend', 'delete'], true) && ! $this->passwordRecentlyConfirmed()) {
            session()->put('url.intended', route('admin.users.show', $this->userId));
            $this->redirect(route('password.confirm'));

            return;
        }
        Flux::modal($action.'-user')->show();
    }

    public function suspend(ModerationService $moderation): void
    {
        abort_unless($this->passwordRecentlyConfirmed(), 403);
        $this->run(fn () => $moderation->suspendAccount($this->user, Auth::user(), $this->reason), 'suspend', 'moderation.admin.suspended_done');
    }

    public function reactivate(ModerationService $moderation): void
    {
        $this->run(fn () => $moderation->reactivateAccount($this->user, Auth::user(), $this->reason), 'reactivate', 'moderation.admin.reactivated_done');
    }

    public function deleteAccount(ModerationService $moderation): void
    {
        abort_unless($this->passwordRecentlyConfirmed(), 403);
        $this->run(fn () => $moderation->deleteAccount($this->user, Auth::user(), $this->reason), 'delete', 'moderation.admin.deleted_done');
    }

    public function revokeToken(ModerationService $moderation): void
    {
        $token = PersonalAccessToken::query()->where('tokenable_id', $this->userId)->findOrFail((string) $this->tokenId);
        $this->run(fn () => $moderation->revokeToken($token, Auth::user(), $this->reason), 'revoke', 'moderation.admin.token_revoked');
    }

    private function run(Closure $action, string $modal, string $done): void
    {
        $this->resetErrorBag();
        try {
            $action();
            $this->notice = __($done);
            Flux::modal($modal.'-user')->close();
            $this->reason = '';
        } catch (ModerationRefused|InvalidTransition $exception) {
            $this->addError('reason', $exception->getMessage());
        }
        unset($this->user, $this->tokens, $this->modules, $this->actionable);
    }

    /** Mot de passe confirmé récemment (même délai que le middleware password.confirm). */
    private function passwordRecentlyConfirmed(): bool
    {
        return time() - (int) session('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 10800);
    }
}; ?>

@php($u = $this->user)
<div class="grid gap-5">
    <div>
        <flux:heading size="xl" level="1"><span class="font-mono" dir="ltr">{{ '@'.$u->handle }}</span>@if ($u->name) · {{ $u->name }}@endif</flux:heading>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
            <x-status-badge :status="$u->status" data-test="user-status" />
            <span>{{ $u->isAdmin() ? __('moderation.role.admin') : __('moderation.role.developer') }}</span>
            <span>{{ __('moderation.admin.joined') }} <x-relative-date :at="$u->created_at" /></span>
            @if ($u->email_verified_at)<span>{{ __('moderation.admin.email_verified') }}</span>@endif
        </div>
        @if ($u->status === \App\Enums\AccountStatus::Suspended && $u->suspension_reason)
            <flux:text class="mt-2">{{ __('moderation.admin.suspension_reason') }} : {{ $u->suspension_reason }}</flux:text>
        @endif
    </div>

    @if ($notice)
        <flux:callout icon="check-circle" color="green" data-test="admin-notice"><flux:callout.heading>{{ $notice }}</flux:callout.heading></flux:callout>
    @endif
    @error('reason')
        <flux:callout icon="exclamation-triangle" color="red" data-test="admin-error"><flux:callout.heading>{{ $message }}</flux:callout.heading></flux:callout>
    @enderror

    <div class="grid gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading>{{ __('moderation.admin.public_modules') }} · {{ $this->modules->count() }}</flux:heading>
        <div class="flex flex-wrap gap-2 font-mono text-sm" dir="ltr">
            @foreach ($this->modules as $module)
                <a href="{{ route('admin.modules.show', $module->id) }}" wire:navigate>{{ $module->name }}</a>
            @endforeach
        </div>
    </div>

    <div class="grid gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="user-tokens">
        <flux:heading>{{ __('tokens.title') }}</flux:heading>
        @forelse ($this->tokens as $token)
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-200 pt-2 text-sm first:border-0 first:pt-0 dark:border-zinc-700" wire:key="t-{{ $token->id }}">
                <span>{{ $token->name }} · <span class="text-zinc-500 dark:text-zinc-400">{{ __('moderation.admin.last_used') }} @if ($token->last_used_at)<x-relative-date :at="$token->last_used_at" />@else — @endif</span></span>
                <flux:button size="sm" variant="danger" wire:click="open('revoke', '{{ $token->id }}')" data-test="revoke-token">{{ __('moderation.admin.revoke_token') }}</flux:button>
            </div>
        @empty
            <flux:text>{{ __('moderation.admin.no_tokens') }}</flux:text>
        @endforelse
    </div>

    @if ($this->actionable)
        <div class="grid gap-3 rounded-lg border border-red-600 p-4" data-test="danger-zone">
            <flux:heading class="text-red-600!">{{ __('moderation.admin.danger_zone') }}</flux:heading>
            <flux:text>{{ __('moderation.admin.danger_text') }}</flux:text>
            <div class="flex flex-wrap gap-2">
                @if ($u->status === \App\Enums\AccountStatus::Active)
                    <flux:button variant="danger" wire:click="open('suspend')" data-test="suspend">{{ __('moderation.admin.suspend') }}</flux:button>
                @else
                    <flux:button wire:click="open('reactivate')" data-test="reactivate">{{ __('moderation.admin.reactivate') }}</flux:button>
                @endif
                <flux:button variant="danger" wire:click="open('delete')" data-test="delete-account">{{ __('moderation.admin.delete') }}</flux:button>
            </div>
        </div>
    @endif

    @foreach ([['suspend', __('moderation.admin.suspend'), 'danger', 'suspend', __('moderation.admin.suspend_text')], ['reactivate', __('moderation.admin.reactivate'), 'primary', 'reactivate', null], ['delete', __('moderation.admin.delete'), 'danger', 'deleteAccount', __('moderation.admin.delete_text')], ['revoke', __('moderation.admin.revoke_token'), 'danger', 'revokeToken', null]] as [$action, $label, $variant, $method, $text])
        <x-confirm-step :name="$action.'-user'" :heading="$label">
            {{ $text }}
            <flux:textarea wire:model="reason" :label="$action === 'suspend' ? __('moderation.admin.suspend_reason') : __('certification.admin.reason')" rows="3" class="mt-3" data-test="reason-{{ $action }}" />
            <x-slot:action><flux:button :variant="$variant" wire:click="{{ $method }}" data-test="confirm-{{ $action }}">{{ $label }}</flux:button></x-slot:action>
        </x-confirm-step>
    @endforeach
</div>
