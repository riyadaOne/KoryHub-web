<?php

use App\Enums\Visibility;
use App\Enums\WatchLevel;
use App\Models\Module;
use App\Models\Star;
use App\Models\Watch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Boutons « Favori » et « Suivre » de la fiche (F-RCH-09, maquettes W05) : enregistrés au clic.
 * Suivre : Toutes les activités, Versions seulement ou Ignorer ; l'auteur ne suit pas son module.
 * Visiteur : les boutons mènent à la connexion. Module privé : rien n'est affiché.
 */
new class extends Component {
    #[Locked]
    public string $moduleId = '';

    #[Computed]
    public function module(): Module
    {
        return Module::query()->findOrFail($this->moduleId);
    }

    #[Computed]
    public function starred(): bool
    {
        return Auth::check() && Star::query()->where('user_id', Auth::id())->where('module_id', $this->moduleId)->exists();
    }

    #[Computed]
    public function starCount(): int
    {
        return Star::query()->where('module_id', $this->moduleId)->count();
    }

    #[Computed]
    public function watchLevel(): ?WatchLevel
    {
        return Auth::check() ? Watch::query()->where('user_id', Auth::id())->where('module_id', $this->moduleId)->value('level') : null;
    }

    public function toggleStar()
    {
        if (! Auth::check()) {
            return $this->redirectRoute('login');
        }
        abort_unless($this->module->visibility === Visibility::Public, 404);

        $existing = Star::query()->where('user_id', Auth::id())->where('module_id', $this->moduleId)->first();
        $existing ? $existing->delete() : Star::create(['user_id' => Auth::id(), 'module_id' => $this->moduleId]);
        unset($this->starred, $this->starCount);
    }

    public function watch(string $level)
    {
        if (! Auth::check()) {
            return $this->redirectRoute('login');
        }
        abort_unless($this->module->visibility === Visibility::Public && $this->module->author_id !== Auth::id(), 403);

        if ($level === 'none') {
            Watch::query()->where('user_id', Auth::id())->where('module_id', $this->moduleId)->delete();
        } else {
            Watch::query()->updateOrCreate(
                ['user_id' => Auth::id(), 'module_id' => $this->moduleId],
                ['level' => WatchLevel::from($level)],
            );
        }
        unset($this->watchLevel);
    }
}; ?>

<div class="flex flex-wrap gap-2">
    @if ($this->module->visibility === \App\Enums\Visibility::Public)
        <flux:button size="sm" :icon="$this->starred ? 'star' : 'star'" :variant="$this->starred ? 'primary' : 'outline'" wire:click="toggleStar" data-test="star-button" aria-pressed="{{ $this->starred ? 'true' : 'false' }}">
            {{ __('community.stars.button') }} · {{ $this->starCount }}
        </flux:button>

        @if ($this->module->author_id !== auth()->id())
            <flux:dropdown>
                <flux:button size="sm" icon:trailing="chevron-down" data-test="watch-button">
                    {{ $this->watchLevel ? __('community.watch.current', ['level' => $this->watchLevel->label()]) : __('community.watch.button') }}
                </flux:button>
                <flux:menu class="w-80">
                    @foreach (\App\Enums\WatchLevel::cases() as $level)
                        <flux:menu.item wire:click="watch('{{ $level->value }}')" :icon="$this->watchLevel === $level ? 'check' : 'minus'" data-test="watch-{{ $level->value }}">
                            <div>
                                <div class="font-medium">{{ $level->label() }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $level->description() }}</div>
                            </div>
                        </flux:menu.item>
                    @endforeach
                    @if ($this->watchLevel)
                        <flux:menu.separator />
                        <flux:menu.item wire:click="watch('none')" icon="x-mark" data-test="watch-none">{{ __('community.watch.stop') }}</flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
        @endif
    @endif
</div>
