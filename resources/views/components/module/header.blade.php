{{--
    En-tête de la fiche d'un kory (S-03 à S-06, maquette exemple-kory.html et maquettes W03) :
    catégorie et auteur, nom, description, badges, actions de l'auteur, bandeaux, onglets.
--}}
@props(['module', 'tab', 'version' => null, 'isAuthor' => false, 'filesCount' => null, 'versionsCount' => null, 'reviewsCount' => null])

@php
    $base = route('modules.show', ['author' => $module->author->handle, 'module' => $module->name]);
    // Nombre de versions quand la page ne le donne pas (versions publiées et dépréciées).
    $versionsCount ??= $module->versions()->whereIn('status', [\App\Enums\VersionStatus::Published, \App\Enums\VersionStatus::Deprecated])->count();
    $tabs = [
        'readme' => [__('modules.tabs.readme'), $base],
        'architecture' => [__('modules.tabs.architecture'), route('modules.architecture', ['author' => $module->author->handle, 'module' => $module->name])],
        'files' => [__('modules.tabs.files').($filesCount !== null ? ' · '.$filesCount : ''), route('modules.files', ['author' => $module->author->handle, 'module' => $module->name])],
        'versions' => [__('modules.tabs.versions').($versionsCount !== null ? ' · '.$versionsCount : ''), route('modules.versions', ['author' => $module->author->handle, 'module' => $module->name])],
    ];
    // Avis (W05) : nombre affiché sauf si l'auteur les a masqués.
    $reviewsCount ??= $module->reviews_hidden ? null : $module->reviews()->count();
    $tabs['reviews'] = [__('community.reviews.tab').($reviewsCount !== null ? ' · '.$reviewsCount : ''), route('modules.reviews', ['author' => $module->author->handle, 'module' => $module->name])];
@endphp

<section class="grid gap-2.5 pb-2">
    <div class="text-sm text-zinc-500 dark:text-zinc-400">
        @if ($module->category)
            {{ $module->category->name() }} ·
        @endif
        <a href="{{ route('profiles.show', $module->author->handle) }}" class="font-mono hover:underline" dir="ltr" wire:navigate>{{ '@'.$module->author->handle }}</a>
    </div>

    <h1 class="m-0 break-all font-mono text-2xl font-semibold" dir="ltr">
        <span class="font-normal text-zinc-500 dark:text-zinc-400">{{ '@'.$module->author->handle }}/</span>{{ $module->name }}
    </h1>

    @if ($module->description)
        <p class="m-0 max-w-prose text-zinc-600 dark:text-zinc-400">{{ $module->description }}</p>
    @endif

    <div class="flex flex-wrap gap-2" data-test="module-badges">
        @if ($version)
            <flux:badge size="sm" :color="$version->status->color()->fluxColor()">{{ $version->status->label().' · '.$version->version }}</flux:badge>
            <flux:badge size="sm" color="zinc">{{ $version->license->spdx() }}</flux:badge>
        @endif
        @if ($module->visibility === \App\Enums\Visibility::Private)
            <x-status-badge :status="$module->visibility" />
        @endif
        @if ($module->status === \App\Enums\ModuleStatus::Archived)
            <x-status-badge :status="$module->status" />
        @endif
        @if ($module->language)
            <flux:badge size="sm" color="zinc">{{ $module->language }}</flux:badge>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        <livewire:module-actions :module-id="$module->id" :key="'actions-'.$module->id" />
        @if ($isAuthor)
            <flux:button size="sm" icon="cog-6-tooth" :href="route('modules.settings', ['author' => $module->author->handle, 'module' => $module->name])" wire:navigate data-test="module-settings-link">{{ __('modules.settings') }}</flux:button>
        @endif
    </div>
</section>

@if ($module->status === \App\Enums\ModuleStatus::Archived)
    <flux:callout icon="archive-box" class="mt-2" data-test="archived-banner">
        <flux:callout.heading>{{ __('modules.banners.archived') }}</flux:callout.heading>
        <flux:callout.text>{{ __('modules.banners.archived_text') }}</flux:callout.text>
    </flux:callout>
@endif

@if ($version?->status === \App\Enums\VersionStatus::Deprecated)
    <x-warning-banner :heading="__('modules.banners.deprecated', ['version' => $version->version])" class="mt-2" data-test="deprecated-banner">
        {{ $version->deprecation_message }}
    </x-warning-banner>
@endif

<nav class="mt-4 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700" aria-label="{{ __('modules.tabs.label') }}">
    @foreach ($tabs as $key => [$label, $href])
        <a href="{{ $href }}" wire:navigate @class([
            'whitespace-nowrap border-b-2 px-3.5 py-2.5 text-sm',
            'border-accent font-semibold text-zinc-900 dark:text-zinc-50' => $tab === $key,
            'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50' => $tab !== $key,
        ]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
