<?php

use App\Enums\Locale;
use App\Models\Category;
use App\Services\Admin\CategoryService;
use App\Services\Moderation\ModerationRefused;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * S-70 Catégories (F-RCH-11, F-ADM-11, maquettes W08) : ordre, nom dans la langue de l'admin,
 * modules qui l'utilisent, statut ; créer (identifiant figé), renommer dans les 4 langues, monter,
 * descendre, désactiver ; supprimer seulement si aucun module ne l'utilise.
 */
new #[Layout('layouts::admin')] #[Title('admin.categories.title')] class extends Component {
    #[Locked]
    public ?string $editingId = null;

    public string $slug = '';

    /** @var array<string, string> */
    public array $names = [];

    public ?string $notice = null;

    #[Computed]
    public function categories()
    {
        return Category::query()->with('translations')->orderBy('position')->get();
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        return \App\Models\Module::withTrashed()->whereNotNull('category_id')->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')->pluck('total', 'category_id')->map(fn ($n) => (int) $n)->all();
    }

    public function create(): void
    {
        $this->resetErrorBag();
        [$this->editingId, $this->slug] = [null, ''];
        $this->names = array_fill_keys(Locale::values(), '');
        Flux::modal('category-form')->show();
    }

    public function edit(string $id): void
    {
        $this->resetErrorBag();
        $category = Category::query()->with('translations')->findOrFail($id);
        [$this->editingId, $this->slug] = [$category->id, $category->slug];
        $this->names = array_merge(array_fill_keys(Locale::values(), ''), $category->translations->pluck('name', 'locale')->all());
        Flux::modal('category-form')->show();
    }

    public function save(CategoryService $categories): void
    {
        $this->run(function () use ($categories) {
            $this->editingId === null
                ? $categories->create($this->slug, $this->names, Auth::user())
                : $categories->rename(Category::query()->findOrFail($this->editingId), $this->names, Auth::user());
            Flux::modal('category-form')->close();
        });
    }

    public function move(string $id, int $direction, CategoryService $categories): void
    {
        $this->run(fn () => $categories->move(Category::query()->findOrFail($id), $direction < 0 ? -1 : 1, Auth::user()));
    }

    public function toggle(string $id, CategoryService $categories): void
    {
        $category = Category::query()->findOrFail($id);
        $this->run(fn () => $categories->setActive($category, ! $category->is_active, Auth::user()));
    }

    public function delete(string $id, CategoryService $categories): void
    {
        $this->run(fn () => $categories->delete(Category::query()->findOrFail($id), Auth::user()));
    }

    private function run(Closure $action): void
    {
        $this->resetErrorBag();
        try {
            $action();
            $this->notice = __('moderation.admin.saved');
        } catch (ModerationRefused $exception) {
            $this->addError('category', $exception->getMessage());
        }
        unset($this->categories, $this->counts);
    }
}; ?>

<div class="grid gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl" level="1">{{ __('admin.categories.title') }}</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="create" data-test="new-category">{{ __('admin.categories.new') }}</flux:button>
    </div>

    @if ($notice)
        <flux:callout icon="check-circle" color="green" data-test="admin-notice"><flux:callout.heading>{{ $notice }}</flux:callout.heading></flux:callout>
    @endif
    @error('category')
        <flux:callout icon="exclamation-triangle" color="red" data-test="admin-error"><flux:callout.heading>{{ $message }}</flux:callout.heading></flux:callout>
    @enderror

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm" data-test="admin-categories">
            <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                <tr>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('admin.categories.order') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('admin.categories.name') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('admin.categories.slug') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.modules') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.status') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->categories as $category)
                    @php($used = $this->counts[$category->id] ?? 0)
                    <tr @class(['border-t border-zinc-200 dark:border-zinc-700', 'text-zinc-500 dark:text-zinc-400' => ! $category->is_active]) wire:key="{{ $category->id }}" data-test="category-{{ $category->slug }}">
                        <td class="whitespace-nowrap px-3 py-2">
                            <flux:button size="xs" icon="arrow-up" wire:click="move('{{ $category->id }}', -1)" :disabled="$loop->first" :aria-label="__('admin.categories.up')" />
                            <flux:button size="xs" icon="arrow-down" wire:click="move('{{ $category->id }}', 1)" :disabled="$loop->last" :aria-label="__('admin.categories.down')" />
                        </td>
                        <td class="px-3 py-2">{{ $category->name() }}</td>
                        <td class="px-3 py-2 font-mono" dir="ltr">{{ $category->slug }}</td>
                        <td class="px-3 py-2">{{ $used }}</td>
                        <td class="px-3 py-2">
                            <flux:badge size="sm" :color="$category->is_active ? 'green' : 'zinc'">{{ $category->is_active ? __('admin.categories.active') : __('admin.categories.inactive') }}</flux:badge>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" wire:click="edit('{{ $category->id }}')" data-test="edit-{{ $category->slug }}">{{ __('admin.categories.edit') }}</flux:button>
                                <flux:button size="sm" wire:click="toggle('{{ $category->id }}')" data-test="toggle-{{ $category->slug }}">{{ $category->is_active ? __('admin.categories.deactivate') : __('admin.categories.activate') }}</flux:button>
                                @if ($used === 0)
                                    <flux:modal.trigger :name="'delete-category-'.$category->id">
                                        <flux:button size="sm" variant="danger" data-test="delete-{{ $category->slug }}">{{ __('admin.categories.delete') }}</flux:button>
                                    </flux:modal.trigger>
                                    <x-confirm-step :name="'delete-category-'.$category->id" :heading="__('admin.categories.delete_title', ['name' => $category->name()])">
                                        <x-slot:action><flux:button variant="danger" wire:click="delete('{{ $category->id }}')" x-on:click="$flux.modals().close()">{{ __('admin.categories.delete') }}</flux:button></x-slot:action>
                                    </x-confirm-step>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <flux:modal name="category-form" class="md:w-[32rem]">
        <form wire:submit="save" class="grid gap-4">
            <flux:heading size="lg">{{ $editingId ? __('admin.categories.edit_title') : __('admin.categories.new') }}</flux:heading>
            @error('category')
                <flux:text class="text-red-600!">{{ $message }}</flux:text>
            @enderror
            <flux:input wire:model="slug" :label="__('admin.categories.slug')" :disabled="$editingId !== null" class="font-mono" data-test="category-slug" />
            @foreach (\App\Enums\Locale::cases() as $locale)
                <flux:input wire:model="names.{{ $locale->value }}" :label="$locale->nativeName()" :dir="$locale->direction()" data-test="category-name-{{ $locale->value }}" />
            @endforeach
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('common.cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-category">{{ __('admin.categories.save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
