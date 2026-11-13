<?php

use App\Concerns\ProfileValidationRules;
use App\Enums\SecurityEventType;
use App\Services\Accounts\Countries;
use App\Services\Accounts\SecurityLog;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Écran Profil du kit (S-37), avec les ajouts de KoryHub (W01) : nom @auteur affiché sans être
 * modifiable, photo de profil, pays. La suppression du compte (S-38) reste sur cette page.
 */
new #[Title('kit.profile_settings')] class extends Component {
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $country = '';
    public $photo = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = (string) Auth::user()->name;
        $this->email = (string) Auth::user()->email;
        $this->country = (string) Auth::user()->country;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(SecurityLog $securityLog, Countries $countries): void
    {
        $user = Auth::user();

        if (config('fortify.lowercase_usernames')) {
            $this->email = Str::lower($this->email);
        }

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'country' => ['nullable', 'string', Rule::in($countries->codes())],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'country' => $validated['country'] ?: null,
        ]);

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $securityLog->record($user, SecurityEventType::EmailChanged);
        }

        Flux::toast(variant: 'success', text: __('kit.profile_updated'));
    }

    /**
     * Photo de profil (F-AUT-16) : jpg, png ou webp, 2 Mo au plus ; l'ancienne est effacée.
     */
    public function updatedPhoto(): void
    {
        $this->validate([
            'photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('koryhub.avatars.max_kilobytes')],
        ]);

        $user = Auth::user();
        $disk = Storage::disk(config('koryhub.avatars.disk'));
        $previous = $user->avatar_path;

        $user->update(['avatar_path' => $this->photo->store('avatars', config('koryhub.avatars.disk'))]);

        if ($previous !== null) {
            $disk->delete($previous);
        }

        $this->reset('photo');
    }

    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->avatar_path !== null) {
            Storage::disk(config('koryhub.avatars.disk'))->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function countryOptions(): array
    {
        return app(Countries::class)->options();
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('kit.profile_settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('kit.profile')" :subheading="__('kit.update_your_name_and_email_address')">
        <div class="my-6 flex items-center gap-4">
            @if (auth()->user()->avatarUrl())
                <img src="{{ auth()->user()->avatarUrl() }}" alt="" class="size-16 rounded-lg object-cover" data-test="avatar-image" />
            @else
                <flux:avatar size="lg" :initials="auth()->user()->initials()" />
            @endif

            <div class="grid gap-1">
                <div class="flex flex-wrap gap-2">
                    <label class="cursor-pointer">
                        <span class="inline-flex items-center rounded-md border border-zinc-200 px-3 py-1.5 text-sm hover:border-accent dark:border-zinc-700">{{ __('account.photo_change') }}</span>
                        <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" data-test="avatar-input" />
                    </label>
                    @if (auth()->user()->avatar_path)
                        <flux:button size="sm" variant="ghost" wire:click="removePhoto">{{ __('account.photo_remove') }}</flux:button>
                    @endif
                </div>
                <flux:text size="sm">{{ __('account.photo_hint') }}</flux:text>
                <flux:error name="photo" />
            </div>
        </div>

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <div class="grid gap-2">
                <flux:input :label="__('account.handle')" :value="'@'.auth()->user()->handle" readonly dir="ltr" class="font-mono" data-test="handle-readonly" />
                <flux:text size="sm">{{ __('account.public_profile', ['url' => config('koryhub.domains.main').'/@'.auth()->user()->handle]) }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('kit.name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('kit.email')" type="email" required autocomplete="email" :description:trailing="__('account.email_private')" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('kit.your_email_address_is_unverified') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('kit.click_here_to_re_send_the') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('kit.a_new_verification_link_has_been_2') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <flux:select wire:model="country" :label="__('account.country')" data-test="country-select">
                <flux:select.option value="">{{ __('account.country_none') }}</flux:select.option>
                @foreach ($this->countryOptions as $code => $countryName)
                    <flux:select.option :value="$code">{{ $countryName }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('kit.save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
