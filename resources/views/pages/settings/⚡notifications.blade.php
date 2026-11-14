<?php

use App\Enums\NotificationType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Préférences de notification (S-43, F-INT-07, TRD §19) : e-mail et cloche par type réglable,
 * enregistrés à chaque clic. Les notifications de sécurité sont toujours envoyées.
 */
new #[Title('notifications.preferences.title')] class extends Component {
    /** @var array<string, array{mail: bool, database: bool}> */
    public array $preferences = [];

    public bool $saved = false;

    public function mount(): void
    {
        $user = Auth::user();

        foreach (NotificationType::adjustable() as $type) {
            $this->preferences[$type->value] = [
                'mail' => $user->wantsNotification($type, 'mail'),
                'database' => $user->wantsNotification($type, 'database'),
            ];
        }
    }

    public function updatedPreferences(mixed $value, string $key): void
    {
        [$type, $channel] = explode('.', $key) + [null, null];
        $type = NotificationType::tryFrom((string) $type);

        if ($type === null || $type->isSecurity() || ! in_array($channel, ['mail', 'database'], true)) {
            return;
        }

        Auth::user()->notificationPreferences()->updateOrCreate(
            ['type' => $type],
            ['mail' => (bool) $this->preferences[$type->value]['mail'], 'database' => (bool) $this->preferences[$type->value]['database']],
        );

        $this->saved = true;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('notifications.preferences.title')" :subheading="__('notifications.preferences.subtitle')" wide>
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm" data-test="notification-preferences">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('notifications.preferences.type') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('notifications.preferences.mail') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('notifications.preferences.database') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_filter([[__('notifications.preferences.group_modules'), 'mine'], [__('notifications.preferences.group_installed'), 'installed'], [__('notifications.preferences.group_watched'), 'watched'], [__('notifications.preferences.group_reports'), 'reports'], [__('notifications.preferences.group_certification'), 'certification'], auth()->user()->isAdmin() ? [__('notifications.preferences.group_admin'), 'admin'] : null]) as [$groupLabel, $group])
                        <tr class="border-t border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <td colspan="3" class="px-3 py-1.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $groupLabel }}</td>
                        </tr>
                        @foreach (array_filter(\App\Enums\NotificationType::adjustable(), fn ($type) => $type->group() === $group) as $type)
                            <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="pref-{{ $type->value }}">
                                <td class="px-3 py-2">{{ $type->label() }}</td>
                                <td class="px-3 py-2"><flux:switch wire:model.live="preferences.{{ $type->value }}.mail" :aria-label="$type->label().' · '.__('notifications.preferences.mail')" data-test="pref-{{ $type->value }}-mail" /></td>
                                <td class="px-3 py-2"><flux:switch wire:model.live="preferences.{{ $type->value }}.database" :aria-label="$type->label().' · '.__('notifications.preferences.database')" data-test="pref-{{ $type->value }}-database" /></td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="border-t border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <td colspan="3" class="px-3 py-1.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ __('notifications.preferences.group_security') }}
                            <flux:badge size="sm" color="zinc" class="ms-2">{{ __('notifications.preferences.always') }}</flux:badge>
                        </td>
                    </tr>
                    <tr class="border-t border-zinc-200 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        <td class="px-3 py-2">{{ __('notifications.preferences.security_row') }}</td>
                        <td class="px-3 py-2"><flux:switch checked disabled :aria-label="__('notifications.preferences.mail')" /></td>
                        <td class="px-3 py-2"><flux:switch checked disabled :aria-label="__('notifications.preferences.database')" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($saved)
            <flux:text class="mt-3" data-test="preferences-saved">{{ __('notifications.preferences.saved') }}</flux:text>
        @endif
    </x-pages::settings.layout>
</section>
