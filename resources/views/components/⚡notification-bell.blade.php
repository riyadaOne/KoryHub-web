<?php

use App\Notifications\NotificationText;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * Cloche de l'en-tête (W02, TRD §19) : nombre de notifications non lues et les 5 dernières.
 * La page complète des notifications (S-31) arrive en W08. Les textes sont traduits à
 * l'affichage à partir du type enregistré (lang/*\/notifications.php).
 */
new class extends Component {
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    #[Computed]
    public function latest()
    {
        return Auth::user()->notifications()->latest()->limit(5)->get();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->unreadCount, $this->latest);
    }

    public function open(string $id): void
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect(NotificationText::url($notification->data['kind'] ?? '', $notification->data['params'] ?? []), navigate: true);
    }
}; ?>

<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
        <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="relative grid size-8 place-items-center rounded-md text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-50" aria-label="{{ __('notifications.bell') }}" data-test="notification-bell">
            <flux:icon name="bell" class="size-5" />
            @if ($this->unreadCount > 0)
                <span class="absolute -end-1 -top-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-red-600 px-1 text-[11px] font-semibold text-white" data-test="notification-count">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</span>
            @endif
        </button>

        <div x-show="open" x-cloak x-transition.opacity class="absolute end-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900" data-test="notification-list">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-4 py-2 dark:border-zinc-700">
                <flux:heading>{{ __('notifications.bell') }}</flux:heading>
                @if ($this->unreadCount > 0)
                    <flux:button size="xs" variant="ghost" wire:click="markAllRead" data-test="mark-all-read">{{ __('notifications.mark_all_read') }}</flux:button>
                @endif
            </div>

            @forelse ($this->latest as $notification)
                @php($kind = $notification->data['kind'] ?? '')
                @php($replace = NotificationText::replacements($notification->data['params'] ?? []))
                <button type="button" wire:click="open('{{ $notification->id }}')" wire:key="notification-{{ $notification->id }}"
                    class="grid w-full grid-cols-[10px_minmax(0,1fr)] gap-2.5 border-b border-zinc-200 px-4 py-2.5 text-start text-sm last:border-b-0 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <span @class(['mt-1.5 size-2 rounded-full', 'bg-accent' => $notification->read_at === null])></span>
                    <span>
                        <span @class(['block', 'font-semibold' => $notification->read_at === null])>{{ __("notifications.{$kind}.title", $replace) }}</span>
                        <x-relative-date :at="$notification->created_at" class="block text-xs text-zinc-500 dark:text-zinc-400" />
                    </span>
                </button>
            @empty
                <flux:text class="px-4 py-6 text-center">{{ __('notifications.empty') }}</flux:text>
            @endforelse
        </div>
</div>
