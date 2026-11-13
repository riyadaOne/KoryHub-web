<?php

use App\Enums\Visibility;
use App\Models\Star;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-44 Favoris (F-RCH-09) : modules mis en favori, les plus récents d'abord ; retirer d'un clic.
 * Un module devenu privé n'apparaît plus (la ligne reste, il revient s'il redevient public).
 */
new #[Title('community.stars.title')] class extends Component {
    use WithPagination;

    #[Computed]
    public function stars()
    {
        return Auth::user()->stars()
            ->whereHas('module', fn ($q) => $q->where('visibility', Visibility::Public))
            ->with('module.author', 'module.latestVersion')
            ->latest()
            ->paginate(30);
    }

    public function remove(string $id): void
    {
        Star::query()->where('user_id', Auth::id())->whereKey($id)->delete();
        unset($this->stars);
    }
}; ?>

<section class="grid gap-6 py-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('community.stars.title') }}</flux:heading>
        <flux:subheading>{{ __('community.stars.subtitle') }}</flux:subheading>
    </div>

    @if ($this->stars->isEmpty())
        <x-empty-state icon="star" :title="__('community.stars.empty')" :description="__('community.stars.empty_text')" data-test="stars-empty" />
    @else
        <div class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-3.5" data-test="stars-list">
            @foreach ($this->stars as $star)
                <x-module.card :module="$star->module" wire:key="star-{{ $star->id }}">
                    <flux:button size="xs" wire:click="remove('{{ $star->id }}')" data-test="unstar-{{ $star->module->name }}">{{ __('community.stars.remove') }}</flux:button>
                </x-module.card>
            @endforeach
        </div>
        <div>{{ $this->stars->links() }}</div>
    @endif
</section>
