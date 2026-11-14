<?php

use App\Notifications\NotificationText;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-31 Notifications (FLX-10, maquettes W08) : toutes les notifications de la cloche, les plus
 * récentes d'abord, 30 par page ; filtre « Non lues » ; tout marquer comme lu. Chaque ligne mène
 * à la page de l'objet et la marque comme lue. Textes traduits à l'affichage.
 */
new #[Title('notifications.page.title')] class extends Component {
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function notifications()
    {
        $query = $this->filter === 'unread' ? Auth::user()->unreadNotifications() : Auth::user()->notifications();

        return $query->latest()->paginate(30);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->notifications, $this->unreadCount);
    }

    public function open(string $id): void
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect(NotificationText::url($notification->data['kind'] ?? '', $notification->data['params'] ?? []), navigate: true);
    }
}; ?>

<div class="grid gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl" level="1">{{ __('notifications.page.title') }}</flux:heading>
        @if ($this->unreadCount > 0)
            <flux:button size="sm" wire:click="markAllRead" data-test="page-mark-all-read">{{ __('notifications.mark_all_read') }}</flux:button>
        @endif
    </div>

    <div class="flex flex-wrap gap-2" role="tablist">
        @foreach ([['all', __('notifications.page.all')], ['unread', __('notifications.page.unread', ['count' => $this->unreadCount])]] as [$key, $label])
            <flux:button size="sm" :variant="$filter === $key ? 'primary' : 'outline'" wire:click="$set('filter', '{{ $key }}')" data-test="filter-{{ $key }}">{{ $label }}</flux:button>
        @endforeach
    </div>

    @if ($this->notifications->isEmpty())
        <x-empty-state icon="bell" :title="__('notifications.empty')" data-test="notifications-empty" />
    @else
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900" data-test="notifications-page">
            @foreach ($this->notifications as $notification)
                @php($kind = $notification->data['kind'] ?? '')
                @php($replace = NotificationText::replacements($notification->data['params'] ?? []))
                <button type="button" wire:click="open('{{ $notification->id }}')" wire:key="n-{{ $notification->id }}"
                    class="grid w-full grid-cols-[10px_minmax(0,1fr)] gap-3 border-b border-zinc-200 px-4 py-3 text-start text-sm last:border-b-0 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <span @class(['mt-1.5 size-2 rounded-full', 'bg-accent' => $notification->read_at === null])></span>
                    <span>
                        <span @class(['block', 'font-semibold' => $notification->read_at === null])>{{ __("notifications.{$kind}.title", $replace) }}</span>
                        <span class="block text-zinc-600 dark:text-zinc-400">{{ __("notifications.{$kind}.mail", $replace) }}</span>
                        <x-relative-date :at="$notification->created_at" class="block text-xs text-zinc-500 dark:text-zinc-400" />
                    </span>
                </button>
            @endforeach
        </div>
        <div>{{ $this->notifications->links() }}</div>
    @endif
</div>
