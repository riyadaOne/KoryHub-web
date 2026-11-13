<?php

use App\Exceptions\InvalidTransition;
use App\Models\PersonalAccessToken;
use App\Services\Cli\CliTokenService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Jetons du CLI (S-41, F-AUT-08, F-AUT-20) : nom, origine, création, expiration, dernière
 * utilisation, statut. Révocation avec une étape de confirmation dans l'écran (UXUI U4).
 * Les jetons révoqués ou expirés restent affichés (historique).
 */
new #[Title('tokens.title')] class extends Component {
    public ?string $revokingId = null;

    /** Message affiché au-dessus de la liste après une révocation. */
    public ?string $notice = null;

    public bool $noticeIsError = false;

    #[Computed]
    public function tokens()
    {
        return Auth::user()->tokens()->latest()->get();
    }

    #[Computed]
    public function revoking(): ?PersonalAccessToken
    {
        return $this->revokingId === null ? null : Auth::user()->tokens()->find($this->revokingId);
    }

    public function confirmRevoke(string $id): void
    {
        $this->revokingId = Auth::user()->tokens()->findOrFail($id)->getKey();
        Flux::modal('revoke-token')->show();
    }

    public function revoke(CliTokenService $tokens): void
    {
        $token = Auth::user()->tokens()->findOrFail($this->revokingId);

        try {
            $tokens->revoke($token, Auth::user());
            [$this->notice, $this->noticeIsError] = [__('tokens.revoked'), false];
        } catch (InvalidTransition $exception) {
            [$this->notice, $this->noticeIsError] = [$exception->getMessage(), true];
        }

        $this->revokingId = null;
        unset($this->tokens);
        Flux::modal('revoke-token')->close();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('tokens.title')" :subheading="__('tokens.subtitle')" wide>
        @if ($notice)
            <flux:callout :variant="$noticeIsError ? 'danger' : 'success'" :icon="$noticeIsError ? 'x-circle' : 'check-circle'" class="mb-4" data-test="tokens-notice">
                <flux:callout.heading>{{ $notice }}</flux:callout.heading>
            </flux:callout>
        @endif

        <div class="flex justify-end">
            <flux:button variant="primary" :href="route('tokens.create')" wire:navigate data-test="new-token-button">{{ __('tokens.new') }}</flux:button>
        </div>

        @if ($this->tokens->isEmpty())
            <x-empty-state icon="key" :title="__('tokens.empty_title')" :description="__('tokens.empty_text')" class="mt-4">
                <x-slot:actions>
                    <x-copy-command command="kory login" />
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm" data-test="tokens-table">
                    <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('tokens.col_name') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('tokens.col_origin') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('tokens.col_created') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('tokens.col_expires') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('tokens.col_last_used') }}</th>
                            <th class="px-3 py-2 text-start font-semibold">{{ __('tokens.col_status') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->tokens as $token)
                            @php($status = $token->status())
                            <tr wire:key="token-{{ $token->id }}" @class(['border-t border-zinc-200 dark:border-zinc-700', 'text-zinc-500 dark:text-zinc-400' => ! $status->isUsable()])>
                                <td class="px-3 py-2 font-semibold">{{ $token->name }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ $token->origin->label() }}</td>
                                <td class="whitespace-nowrap px-3 py-2"><x-relative-date :at="$token->created_at" /></td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($token->expires_at)
                                        <x-relative-date :at="$token->expires_at" />
                                    @else
                                        {{ __('tokens.never') }}
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($token->last_used_at)
                                        <x-relative-date :at="$token->last_used_at" />
                                    @else
                                        {{ __('tokens.never_used') }}
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2"><x-status-badge :status="$status" /></td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">
                                    @if ($status->isUsable())
                                        <flux:button size="xs" wire:click="confirmRevoke('{{ $token->id }}')" data-test="revoke-token-{{ $token->id }}">{{ __('tokens.revoke') }}</flux:button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-confirm-step name="revoke-token" :heading="__('tokens.revoke_title', ['name' => $this->revoking?->name ?? ''])">
            {{ __('tokens.revoke_text') }}

            <x-slot:action>
                <flux:button variant="danger" wire:click="revoke" data-test="confirm-revoke-token">{{ __('tokens.revoke') }}</flux:button>
            </x-slot:action>
        </x-confirm-step>
    </x-pages::settings.layout>
</section>
