{{-- Bannière d'avertissement (UXUI §6.2, U5) : la cause en titre, ce qu'il faut faire en texte. --}}
@props(['variant' => 'warning', 'heading'])

<flux:callout :variant="$variant === 'danger' ? 'danger' : 'warning'" :icon="$variant === 'danger' ? 'x-circle' : 'exclamation-triangle'" {{ $attributes }}>
    <flux:callout.heading>{{ $heading }}</flux:callout.heading>
    @if ($slot->isNotEmpty())
        <flux:callout.text>{{ $slot }}</flux:callout.text>
    @endif
</flux:callout>
