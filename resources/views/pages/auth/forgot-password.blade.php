<x-layouts::auth :title="__('kit.forgot_password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('kit.forgot_password')" :description="__('kit.enter_your_email_to_receive_a')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('kit.email_address')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('kit.email_password_reset_link') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('kit.or_return_to') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('kit.log_in_lower') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
