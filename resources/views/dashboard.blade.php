{{-- Tableau de bord (S-30, maquette W01) : vide en W01 ; les cartes « à faire » s'ajoutent module par module. --}}
<x-layouts::app :title="__('common.dashboard')">
    <div class="grid gap-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('account.dashboard.greeting', ['name' => auth()->user()->displayName()]) }}</flux:heading>
            <flux:text class="mt-1">{{ __('account.dashboard.subtitle') }}</flux:text>
        </div>

        @unless (auth()->user()->hasVerifiedEmail())
            <x-warning-banner :heading="__('account.dashboard.unverified')" data-test="unverified-banner">
                {{ __('account.dashboard.unverified_action') }}
                <flux:link :href="route('verification.notice')">{{ __('account.dashboard.resend') }}</flux:link>
            </x-warning-banner>
        @endunless

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('account.dashboard.step_terminal') }}</flux:heading>
                <flux:text>{{ __('account.dashboard.step_terminal_text') }}</flux:text>
                <x-copy-command command="kory login" />
            </div>
            <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('account.dashboard.step_publish') }}</flux:heading>
                <flux:text>{{ __('account.dashboard.step_publish_text') }}</flux:text>
                <x-copy-command command="kory init" />
            </div>
        </div>

        <x-empty-state icon="cube" :title="__('account.dashboard.empty_title')" :description="__('account.dashboard.empty_text')" />
    </div>
</x-layouts::app>
