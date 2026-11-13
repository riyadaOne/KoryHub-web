<x-layouts::auth :title="__('kit.log_in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('kit.log_in_to_your_account')" :description="__('kit.enter_your_email_and_password_below')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('kit.email_address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('kit.password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('kit.password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('kit.forgot_your_password') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('kit.remember_me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('kit.log_in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('kit.don_t_have_an_account') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('kit.sign_up') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
