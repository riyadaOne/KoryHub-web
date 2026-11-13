{{-- État vide (UXUI §6.2) : une icône, une phrase, une action utile. --}}
@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center gap-3 rounded-lg border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700') }}>
    <flux:icon :name="$icon" class="size-8 text-zinc-400" />
    <flux:heading>{{ $title }}</flux:heading>
    @if ($description)
        <flux:text>{{ $description }}</flux:text>
    @endif
    @isset($actions)
        <div class="mt-2 flex flex-wrap justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
