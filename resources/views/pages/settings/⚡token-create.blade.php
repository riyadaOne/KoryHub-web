<?php

use App\Enums\TokenOrigin;
use App\Services\Cli\CliTokenService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Nouveau jeton (S-42, F-AUT-05) : nom et durée (90 jours proposés), bannière si « sans
 * expiration ». Mot de passe redemandé avant d'entrer (middleware password.confirm, T-39).
 * Le jeton n'est affiché qu'une fois, ici, puis seule son empreinte est gardée (F-AUT-09).
 */
new #[Title('tokens.create_title')] class extends Component {
    public string $name = '';

    /** Durée en jours, ou « none » pour un jeton sans expiration. */
    public string $lifetime = '90';

    #[Locked]
    public ?string $plainToken = null;

    #[Locked]
    public ?string $createdName = null;

    public function create(CliTokenService $tokens): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'lifetime' => ['required', 'in:7,30,90,365,none'],
        ], attributes: ['name' => __('tokens.name'), 'lifetime' => __('tokens.expiration')]);

        $days = $validated['lifetime'] === 'none' ? null : (int) $validated['lifetime'];

        [$this->plainToken, $token] = $tokens->create(Auth::user(), $validated['name'], $days, TokenOrigin::Site);
        $this->createdName = $token->name;
    }

    /** Date d'expiration affichée sous le choix de la durée. */
    public function expiresOn(): ?string
    {
        return $this->lifetime === 'none'
            ? null
            : now()->addDays((int) $this->lifetime)->locale(app()->getLocale())->isoFormat('LL');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('tokens.create_title')" :subheading="__('tokens.create_subtitle', ['command' => 'kory login'])">
        @if ($plainToken)
            <div class="flex flex-col gap-4" data-test="token-reveal">
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.heading>{{ __('tokens.created_title', ['name' => $createdName]) }}</flux:callout.heading>
                    <flux:callout.text>{{ __('tokens.created_copy') }}</flux:callout.text>
                </flux:callout>

                <x-copy-command :command="$plainToken" data-test="plain-token" />

                <flux:text>{{ __('tokens.created_server') }}</flux:text>
                <x-copy-command command='echo "$KORY_TOKEN" | kory login --with-token' />

                <div>
                    <flux:button :href="route('tokens.index')" wire:navigate>{{ __('tokens.created_done') }}</flux:button>
                </div>
            </div>
        @else
            <form wire:submit="create" class="flex flex-col gap-6">
                <flux:input wire:model="name" :label="__('tokens.name')" required maxlength="100" autofocus data-test="token-name" />

                <flux:radio.group wire:model.live="lifetime" :label="__('tokens.expiration')" variant="segmented" data-test="token-lifetime">
                    @foreach (['7', '30', '90', '365'] as $days)
                        <flux:radio :value="$days">{{ __('tokens.days', ['count' => $days]) }}</flux:radio>
                    @endforeach
                    <flux:radio value="none">{{ __('tokens.no_expiry') }}</flux:radio>
                </flux:radio.group>

                @if ($this->expiresOn())
                    <flux:text class="-mt-4">{{ __('tokens.expires_on', ['date' => $this->expiresOn()]) }}</flux:text>
                @else
                    <x-warning-banner :heading="__('tokens.no_expiry_title')" data-test="no-expiry-warning">
                        {{ __('tokens.no_expiry_text') }}
                    </x-warning-banner>
                @endif

                <div class="flex gap-2">
                    <flux:button variant="primary" type="submit" data-test="create-token-button">{{ __('tokens.create') }}</flux:button>
                    <flux:button :href="route('tokens.index')" wire:navigate variant="ghost">{{ __('tokens.cancel') }}</flux:button>
                </div>
            </form>
        @endif
    </x-pages::settings.layout>
</section>
