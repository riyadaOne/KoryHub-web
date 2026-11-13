<x-layouts::auth :title="__('kit.register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('kit.create_an_account')" :description="__('kit.enter_your_details_below_to_create')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('kit.name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('kit.full_name')"
            />

            <!-- Nom @auteur (KoryHub, W01) : la règle n'est pas affichée, le validateur la renvoie -->
            <div class="grid gap-2">
                <flux:input
                    name="handle"
                    :label="__('account.handle')"
                    :value="old('handle')"
                    type="text"
                    required
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    dir="ltr"
                    placeholder="moussa"
                    data-test="handle-input"
                />
                <flux:text size="sm">{{ __('account.handle_hint', ['example' => '@'.(old('handle') ?: 'moussa').'/…']) }}</flux:text>
            </div>

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('kit.email_address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('kit.password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('kit.password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('kit.confirm_password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('kit.confirm_password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('kit.create_account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('kit.already_have_an_account') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('kit.log_in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
