<?php

use App\Enums\NotificationType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * S-73 Réglages de l'admin (F-ADM-11, décision W08 3a) : seuls les canaux de ses alertes se
 * règlent ici (le groupe « Administration » a quitté S-43) ; conservation, sauvegardes et rôles
 * ne sont pas réglables. L'incident part toujours par e-mail.
 */
new #[Layout('layouts::admin')] #[Title('admin.settings.title')] class extends Component {
    /** @var array<string, array{mail: bool, database: bool}> */
    public array $preferences = [];

    public bool $saved = false;

    public function mount(): void
    {
        foreach ($this->types() as $type) {
            $this->preferences[$type->value] = [
                'mail' => Auth::user()->wantsNotification($type, 'mail'),
                'database' => Auth::user()->wantsNotification($type, 'database'),
            ];
        }
    }

    /** @return list<NotificationType> */
    public function types(): array
    {
        return array_values(array_filter(NotificationType::cases(), fn (NotificationType $type) => $type->group() === 'admin'));
    }

    public function save(): void
    {
        foreach ($this->types() as $type) {
            Auth::user()->notificationPreferences()->updateOrCreate(
                ['type' => $type],
                ['mail' => $type->alwaysByMail() || (bool) ($this->preferences[$type->value]['mail'] ?? false), 'database' => (bool) ($this->preferences[$type->value]['database'] ?? false)],
            );
        }
        Auth::user()->unsetRelation('notificationPreferences');
        $this->saved = true;
    }
}; ?>

<div class="grid gap-5">
    <div>
        <flux:heading size="xl" level="1">{{ __('admin.settings.title') }}</flux:heading>
        <flux:text class="mt-1">{{ __('admin.settings.subtitle') }}</flux:text>
    </div>

    @if ($saved)
        <flux:callout icon="check-circle" color="green" data-test="admin-notice"><flux:callout.heading>{{ __('moderation.admin.saved') }}</flux:callout.heading></flux:callout>
    @endif

    <form wire:submit="save" class="grid max-w-2xl gap-4">
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm" data-test="admin-preferences">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('admin.settings.alert') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('notifications.preferences.mail') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('notifications.preferences.database') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->types() as $type)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="{{ $type->value }}">
                            <td class="px-3 py-2">{{ $type->label() }}</td>
                            <td class="px-3 py-2">
                                @if ($type->alwaysByMail())
                                    <span class="inline-flex items-center gap-2"><flux:switch checked disabled :aria-label="__('notifications.preferences.mail')" /> <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('notifications.preferences.always') }}</span></span>
                                @else
                                    <flux:switch wire:model="preferences.{{ $type->value }}.mail" :aria-label="__('notifications.preferences.mail')" data-test="mail-{{ $type->value }}" />
                                @endif
                            </td>
                            <td class="px-3 py-2"><flux:switch wire:model="preferences.{{ $type->value }}.database" :aria-label="__('notifications.preferences.database')" data-test="database-{{ $type->value }}" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div><flux:button type="submit" variant="primary" data-test="save-admin-settings">{{ __('admin.settings.save') }}</flux:button></div>
    </form>
</div>
