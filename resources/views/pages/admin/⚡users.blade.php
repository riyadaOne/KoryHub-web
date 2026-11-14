<?php

use App\Enums\AccountStatus;
use App\Enums\Visibility;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-67 Comptes (F-ADM-02) : recherche par identifiant ou e-mail, filtre de statut ; rôle,
 * statut, modules publics, inscription. Les comptes supprimés restent listés, sans donnée personnelle.
 */
new #[Layout('layouts::admin')] #[Title('moderation.admin.users')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $q = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        $q = str_replace(['%', '_'], ['\%', '\_'], trim($this->q));

        return User::query()
            ->when(AccountStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('handle', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')))
            ->withCount(['modules as public_modules_count' => fn ($m) => $m->where('visibility', Visibility::Public)])
            ->latest()
            ->paginate(30);
    }
}; ?>

<div class="grid gap-5">
    <flux:heading size="xl" level="1">{{ __('moderation.admin.users') }}</flux:heading>

    <div class="flex flex-wrap items-center gap-2">
        @foreach ([['all', __('moderation.admin.all')], ['active', __('account.status.active')], ['suspended', __('account.status.suspended')], ['deleted', __('account.status.deleted')]] as [$key, $label])
            <flux:button size="sm" :variant="$status === $key ? 'primary' : 'outline'" wire:click="$set('status', '{{ $key }}')" data-test="status-{{ $key }}">{{ $label }}</flux:button>
        @endforeach
        <flux:input wire:model.live.debounce.300ms="q" icon="magnifying-glass" :placeholder="__('moderation.admin.search_user')" class="max-w-xs" data-test="user-search" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm" data-test="admin-users">
            <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                <tr>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.target.user') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.role') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.status') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.public_modules') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.joined') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->users as $user)
                    <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="{{ $user->id }}">
                        <td class="whitespace-nowrap px-3 py-2">
                            <a href="{{ route('admin.users.show', $user->id) }}" class="font-mono font-semibold" dir="ltr" wire:navigate>{{ '@'.$user->handle }}</a>
                            @if ($user->name)<span class="text-zinc-500 dark:text-zinc-400"> · {{ $user->name }}</span>@endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2">{{ $user->isAdmin() ? __('moderation.role.admin') : __('moderation.role.developer') }}</td>
                        <td class="px-3 py-2"><x-status-badge :status="$user->status" /></td>
                        <td class="px-3 py-2">{{ $user->public_modules_count }}</td>
                        <td class="whitespace-nowrap px-3 py-2"><x-relative-date :at="$user->created_at" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div>{{ $this->users->links() }}</div>
</div>
