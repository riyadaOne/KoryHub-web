<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Services\Accounts\AccountService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/*
 * Suppression du compte (S-38, F-AUT-17) : confirmation dans l'écran, en tapant son nom @auteur
 * et son mot de passe ; jamais d'alerte native. Données personnelles effacées, nom réservé.
 */
new class extends Component {
    use PasswordValidationRules;

    public string $handle = '';
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout, AccountService $accounts): void
    {
        $user = Auth::user();

        $this->validate([
            'handle' => ['required', 'in:'.$user->handle],
            'password' => $this->currentPasswordRules(),
        ], [
            'handle.in' => __('account.delete.mismatch'),
        ]);

        $logout();
        $accounts->delete($user);

        session()->flash('status', __('account.delete.done'));

        $this->redirect(route('home'), navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6" x-data="{ typed: '' }">
        <div>
            <flux:heading size="lg">{{ __('account.delete.confirm_title') }}</flux:heading>

            <flux:subheading>
                {{ __('account.delete.confirm_text', ['handle' => auth()->user()->handle]) }}
            </flux:subheading>
        </div>

        <flux:input wire:model="handle" x-model="typed" :label="__('account.delete.type_handle', ['handle' => auth()->user()->handle])" autocomplete="off" dir="ltr" class="font-mono" data-test="confirm-handle-input" />

        <flux:input wire:model="password" :label="__('kit.password')" type="password" viewable />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('kit.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" :loading="false" data-test="confirm-delete-user-button" x-bind:disabled="typed !== @js(auth()->user()->handle)">
                {{ __('kit.delete_account') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
