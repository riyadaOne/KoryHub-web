{{--
    Carte d'un kory (S-01, S-02, S-08, S-09, S-44, maquettes W05) : nom, description, langage,
    note vérifiée (sauf avis masqués), téléchargements, dernière version ; créneau pour une action.
--}}
@props(['module'])

<article {{ $attributes->class('grid min-w-0 gap-1.5 rounded-lg border border-zinc-200 bg-white px-4 py-3.5 dark:border-zinc-700 dark:bg-zinc-900') }}>
    <a href="{{ route('modules.show', ['author' => $module->author->handle, 'module' => $module->name]) }}" class="break-all font-mono text-sm font-semibold" dir="ltr" wire:navigate>
        <span class="font-normal text-zinc-500 dark:text-zinc-400">{{ '@'.$module->author->handle }}/</span>{{ $module->name }}
    </a>
    @if ($module->description)
        <p class="m-0 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400" dir="auto">{{ $module->description }}</p>
    @endif
    <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
        @if ($module->latestVersion)
            <span class="font-mono" dir="ltr">{{ $module->latestVersion->version }}</span>
            @if ($module->latestVersion->isCertified())
                <flux:badge size="sm" color="green" icon="check-badge">{{ __('certification.badge') }}</flux:badge>
            @endif
        @endif
        @if ($module->language)
            <span>{{ $module->language }}</span>
        @endif
        @if (! $module->reviews_hidden && $module->rating_avg !== null)
            <span>★ {{ \Illuminate\Support\Number::format($module->rating_avg, 1) }}</span>
        @endif
        <span>↓ {{ \Illuminate\Support\Number::format($module->download_count) }}</span>
        @if ($module->status === \App\Enums\ModuleStatus::Archived)
            <x-status-badge :status="$module->status" />
        @endif
        {{ $slot }}
    </div>
</article>
