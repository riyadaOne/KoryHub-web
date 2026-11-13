<?php

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Écran Apparence du kit (S-49), avec la langue de l'interface et des e-mails (W01).
 */
new #[Title('kit.appearance_settings')] class extends Component {
    public string $locale = '';

    public function mount(): void
    {
        $this->locale = Auth::user()->locale;
    }

    /** Enregistre la langue sur le compte et dans le cookie, puis recharge la page dans cette langue. */
    public function updatedLocale(string $value): void
    {
        $locale = Locale::tryFrom($value);

        if ($locale === null) {
            $this->locale = Auth::user()->locale;

            return;
        }

        Auth::user()->update(['locale' => $locale->value]);
        Cookie::queue(SetLocale::COOKIE, $locale->value, 60 * 24 * 365);

        $this->redirect(route('appearance.edit'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('kit.appearance_settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('kit.appearance')" :subheading="__('kit.update_the_appearance_settings_for_your')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('kit.light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('kit.dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('kit.system') }}</flux:radio>
        </flux:radio.group>

        <div class="mt-10 space-y-1">
            <flux:heading>{{ __('account.language.title') }}</flux:heading>
            <flux:subheading>{{ __('account.language.subtitle') }}</flux:subheading>
        </div>

        <flux:radio.group wire:model.live="locale" variant="segmented" class="mt-4" data-test="locale-radios">
            @foreach (Locale::cases() as $option)
                <flux:radio :value="$option->value" lang="{{ $option->value }}">{{ $option->nativeName() }}</flux:radio>
            @endforeach
        </flux:radio.group>
    </x-pages::settings.layout>
</section>
