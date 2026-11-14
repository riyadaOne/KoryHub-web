<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('kit.settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('kit.profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('kit.security') }}</flux:navlist.item>
            <flux:navlist.item :href="route('tokens.index')" wire:navigate data-test="tokens-link">{{ __('tokens.title') }}</flux:navlist.item>
            <flux:navlist.item :href="route('notifications.edit')" wire:navigate data-test="notifications-link">{{ __('notifications.preferences.title') }}</flux:navlist.item>
            <flux:navlist.item :href="route('downloads.index')" wire:navigate data-test="downloads-link">{{ __('downloads.title') }}</flux:navlist.item>
            <flux:navlist.item :href="route('reports.mine')" wire:navigate data-test="reports-link">{{ __('moderation.mine.title') }}</flux:navlist.item>
            <flux:navlist.item :href="route('activity.index')" wire:navigate data-test="activity-link">{{ __('audit.mine.title') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security-log.index')" wire:navigate data-test="security-log-link">{{ __('account.security_log.title') }}</flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('kit.appearance') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div @class(['mt-5 w-full', 'max-w-lg' => ! ($wide ?? false)])>
            {{ $slot }}
        </div>
    </div>
</div>
