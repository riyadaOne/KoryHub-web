<?php

use App\Models\CliDeviceCode;
use App\Services\Cli\DeviceAuthorizationService;
use App\Services\Cli\DeviceCodeUnavailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Autoriser un terminal (S-26, F-AUT-06, TRD §7.3) : ouvert par kory login avec le code déjà
 * rempli ; le développeur connecté vérifie le code, choisit la durée du jeton, autorise ou refuse.
 * Saisies de code limitées à 10 par minute et par compte.
 */
new #[Layout('layouts::auth')] #[Title('tokens.device_title')] class extends Component {
    #[Url(as: 'code', except: '')]
    public string $code = '';

    public string $lifetime = '90';

    /** enter, confirm, approved ou denied. */
    #[Locked]
    public string $step = 'enter';

    #[Locked]
    public ?string $deviceId = null;

    #[Locked]
    public ?string $approvedUntil = null;

    public function mount(DeviceAuthorizationService $devices): void
    {
        if ($this->code !== '') {
            $this->check($devices);
        }
    }

    /** Vérifie le code saisi ou reçu dans l'adresse. */
    public function check(DeviceAuthorizationService $devices): void
    {
        $this->resetErrorBag('code');
        $key = 'device-code:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('code', __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        $device = $devices->findPending($this->code);

        if ($device === null) {
            RateLimiter::hit($key);
            $this->step = 'enter';
            $this->addError('code', __('tokens.device_invalid'));

            return;
        }

        $this->code = $device->user_code;
        $this->deviceId = $device->getKey();
        $this->step = 'confirm';
    }

    public function approve(DeviceAuthorizationService $devices): void
    {
        $this->validate(['lifetime' => ['required', 'in:7,30,90,365,none']]);
        $days = $this->lifetime === 'none' ? null : (int) $this->lifetime;

        if (! $this->decide(fn (CliDeviceCode $device) => $devices->approve($device, Auth::user(), $days))) {
            return;
        }

        $this->approvedUntil = $days === null ? null : now()->addDays($days)->locale(app()->getLocale())->isoFormat('LL');
        $this->step = 'approved';
    }

    public function deny(DeviceAuthorizationService $devices): void
    {
        if ($this->decide(fn (CliDeviceCode $device) => $devices->deny($device, Auth::user()))) {
            $this->step = 'denied';
        }
    }

    #[Computed]
    public function device(): ?CliDeviceCode
    {
        return $this->deviceId === null ? null : CliDeviceCode::find($this->deviceId);
    }

    /** Applique la décision ; code devenu invalide entre-temps : retour à la saisie. */
    private function decide(\Closure $action): bool
    {
        $device = $this->device;

        try {
            if ($device === null) {
                throw new DeviceCodeUnavailable;
            }
            $action($device);

            return true;
        } catch (DeviceCodeUnavailable) {
            $this->step = 'enter';
            $this->addError('code', __('tokens.device_invalid'));

            return false;
        }
    }
}; ?>

<div class="flex flex-col gap-6">
    @if ($step === 'approved')
        <x-auth-header :title="__('tokens.device_approved_title')" :description="__('tokens.device_approved_text')" />
        <x-auth-session-status class="text-center" data-test="device-approved"
            :status="$approvedUntil ? __('tokens.device_approved_status', ['date' => $approvedUntil]) : __('tokens.device_approved_forever')" />
        <div class="text-center text-sm">
            <flux:link :href="route('tokens.index')" wire:navigate>{{ __('tokens.see_tokens') }}</flux:link>
        </div>
    @elseif ($step === 'denied')
        <x-auth-header :title="__('tokens.device_denied_title')" :description="__('tokens.device_denied_text')" />
    @elseif ($step === 'confirm')
        <x-auth-header :title="__('tokens.device_title')" :description="__('tokens.device_check')" />

        <div class="self-center rounded-lg border border-zinc-200 bg-white px-5 py-2 font-mono text-3xl tracking-widest dark:border-zinc-700 dark:bg-zinc-900" dir="ltr" data-test="device-user-code">{{ $code }}</div>
        <flux:text class="text-center" dir="ltr">{{ $this->device?->client_info ?? __('tokens.device_unknown_terminal') }}</flux:text>

        <div class="flex flex-col gap-4">
            <flux:select wire:model="lifetime" :label="__('tokens.device_expiration')" data-test="device-lifetime">
                @foreach (['7', '30', '90', '365'] as $days)
                    <flux:select.option :value="$days">{{ __('tokens.days', ['count' => $days]) }}</flux:select.option>
                @endforeach
                <flux:select.option value="none">{{ __('tokens.no_expiry') }}</flux:select.option>
            </flux:select>

            <flux:button variant="primary" wire:click="approve" class="w-full" data-test="device-approve">{{ __('tokens.device_approve') }}</flux:button>
            <flux:button wire:click="deny" class="w-full" data-test="device-deny">{{ __('tokens.device_deny') }}</flux:button>
        </div>

        <flux:text class="text-center">{{ __('tokens.device_as', ['handle' => '@'.auth()->user()->handle]) }}</flux:text>
    @else
        <x-auth-header :title="__('tokens.device_title')" :description="__('tokens.device_enter')" />

        <form wire:submit="check" class="flex flex-col gap-6">
            <flux:input wire:model="code" :label="__('tokens.device_code')" class="font-mono" dir="ltr" autocomplete="off" required data-test="device-code-input" />
            <flux:button variant="primary" type="submit" class="w-full">{{ __('tokens.device_continue') }}</flux:button>
        </form>
    @endif
</div>
