<?php

use App\Models\Category;
use App\Models\Module;
use App\Services\Search\ModuleSearch;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-02 Recherche (F-RCH-01, F-RCH-04, F-RCH-05, F-RCH-10) : mots, filtres catégorie, langage et
 * certifiés, tri ; filtres dans l'adresse (partageable, décision W05 5a) ; 20 ou 50 résultats par
 * page, retenu dans le navigateur. Modules privés et versions retirées jamais affichés.
 */
new #[Title('search.title')] class extends Component {
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $language = '';

    #[Url]
    public bool $certified = false;

    #[Url]
    public string $sort = 'relevance';

    public int $perPage = 20;

    public function updated(string $property): void
    {
        if ($property === 'perPage' && ! in_array($this->perPage, config('koryhub.search.per_page'), true)) {
            $this->perPage = 20;
        }
        $this->resetPage();
    }

    #[Computed]
    public function results()
    {
        return app(ModuleSearch::class)->search($this->filters(), $this->perPage, $this->getPage());
    }

    #[Computed]
    public function categories()
    {
        $counts = app(ModuleSearch::class)->categoryCounts($this->filters());

        return Category::query()->where('is_active', true)->orderBy('position')->with('translations')->get()
            ->map(fn (Category $c) => ['slug' => $c->slug, 'name' => $c->name(), 'count' => (int) ($counts[$c->id] ?? 0)]);
    }

    #[Computed]
    public function languages()
    {
        return Module::query()->where('visibility', 'public')->whereNotNull('language')->distinct()->orderBy('language')->pluck('language');
    }

    private function filters(): array
    {
        return ['q' => $this->q, 'category' => $this->category ?: null, 'language' => $this->language ?: null, 'certified' => $this->certified, 'sort' => $this->sort];
    }
}; ?>

<div class="grid gap-6 py-6">
    <form wire:submit="$refresh" class="flex max-w-2xl gap-2" role="search">
        <flux:input wire:model.live.debounce.400ms="q" :placeholder="__('search.placeholder')" icon="magnifying-glass" class="flex-1" :aria-label="__('search.placeholder')" data-test="search-input" />
        <flux:button type="submit" variant="primary">{{ __('search.submit') }}</flux:button>
    </form>

    <div class="grid gap-7 md:grid-cols-[230px_minmax(0,1fr)]">
        <aside class="grid content-start gap-4 text-sm" data-test="search-filters">
            <div>
                <flux:heading size="sm" class="mb-1">{{ __('search.category') }}</flux:heading>
                <div class="grid gap-1">
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="category" value="" class="accent-teal-700"> {{ __('search.all') }}</label>
                    @foreach ($this->categories as $cat)
                        <label class="flex items-center gap-2" wire:key="cat-{{ $cat['slug'] }}">
                            <input type="radio" wire:model.live="category" value="{{ $cat['slug'] }}" class="accent-teal-700">
                            {{ $cat['name'] }} <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $cat['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <flux:heading size="sm" class="mb-1">{{ __('search.language') }}</flux:heading>
                <div class="grid gap-1">
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="language" value="" class="accent-teal-700"> {{ __('search.all') }}</label>
                    @foreach ($this->languages as $lang)
                        <label class="flex items-center gap-2" wire:key="lang-{{ $lang }}"><input type="radio" wire:model.live="language" value="{{ $lang }}" class="accent-teal-700"> {{ $lang }}</label>
                    @endforeach
                </div>
            </div>
            <div>
                <flux:heading size="sm" class="mb-1">{{ __('search.certification') }}</flux:heading>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="certified" class="accent-teal-700"> {{ __('search.certified_only') }}</label>
            </div>
        </aside>

        <div class="grid min-w-0 content-start gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm text-zinc-500 dark:text-zinc-400" data-test="search-total">{{ trans_choice('search.total', $this->results->total(), ['count' => \Illuminate\Support\Number::format($this->results->total())]) }}</span>
                <div class="flex gap-2" x-data x-init="(() => { try { const p = parseInt(localStorage.getItem('kory.search.perPage')); if (p && p !== $wire.perPage) { $wire.set('perPage', p) } } catch (e) {} })()">
                    <select wire:model.live="sort" class="rounded-md border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900" aria-label="{{ __('search.sort') }}" data-test="search-sort">
                        @foreach (['relevance' => __('search.sorts.relevance'), 'downloads' => __('search.sorts.downloads'), 'rating' => __('search.sorts.rating'), 'recent' => __('search.sorts.recent')] as $option => $label)
                            <option value="{{ $option }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="perPage" x-on:change="(() => { try { localStorage.setItem('kory.search.perPage', $event.target.value) } catch (e) {} })()" class="rounded-md border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900" aria-label="{{ __('search.per_page') }}">
                        @foreach (config('koryhub.search.per_page') as $size)
                            <option value="{{ $size }}">{{ __('search.per_page_option', ['count' => $size]) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($this->results->isEmpty())
                <x-empty-state icon="magnifying-glass" :title="$q !== '' ? __('search.empty', ['q' => $q]) : __('search.empty_filters')" :description="__('search.empty_text')" data-test="search-empty">
                    <x-slot:actions><flux:button size="sm" :href="route('search')" wire:navigate>{{ __('search.reset') }}</flux:button></x-slot:actions>
                </x-empty-state>
            @else
                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900" data-test="search-results">
                    @foreach ($this->results as $module)
                        <article class="grid gap-1 px-4 py-3.5" wire:key="r-{{ $module->id }}">
                            <a href="{{ route('modules.show', ['author' => $module->author->handle, 'module' => $module->name]) }}" class="break-all font-mono text-sm font-semibold" dir="ltr" wire:navigate>{{ $module->fullName() }}</a>
                            @if ($module->description)
                                <p class="m-0 text-sm text-zinc-600 dark:text-zinc-400">{{ $module->description }}</p>
                            @endif
                            <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                @if ($module->latestVersion)
                                    <flux:badge size="sm" :color="$module->latestVersion->status->color()->fluxColor()" data-test="result-version">{{ $module->latestVersion->version }}@if ($module->latestVersion->status === \App\Enums\VersionStatus::Deprecated) · {{ $module->latestVersion->status->label() }}@endif</flux:badge>
                                @endif
                                @if ($module->latestVersion?->isCertified())
                                    <flux:badge size="sm" color="green" icon="check-badge" data-test="result-certified">{{ __('certification.badge') }}</flux:badge>
                                @endif
                                @if ($module->status === \App\Enums\ModuleStatus::Archived)
                                    <x-status-badge :status="$module->status" />
                                @endif
                                @if ($module->language)<span>{{ $module->language }}</span>@endif
                                @if (! $module->reviews_hidden && $module->rating_avg !== null)<span>★ {{ \Illuminate\Support\Number::format($module->rating_avg, 1) }} ({{ $module->rating_count }})</span>@endif
                                <span>↓ {{ \Illuminate\Support\Number::format($module->download_count) }}</span>
                                @if ($module->category)<span>{{ $module->category->name() }}</span>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <div>{{ $this->results->links() }}</div>
            @endif
        </div>
    </div>
</div>
