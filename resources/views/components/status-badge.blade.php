{{-- Badge de statut (UXUI §6.2, §8) : libellé et couleur lus dans l'enum, jamais dans la vue. --}}
@props(['status'])

<flux:badge :color="$status->color()->fluxColor()" size="sm" {{ $attributes }}>{{ $status->label() }}</flux:badge>
