{{-- Note en étoiles (S-07, cartes) : valeur de 0 à 5, arrondie à l'étoile la plus proche ; texte lisible par les lecteurs d'écran. --}}
@props(['value' => 0])

@php($filled = (int) round((float) $value))
<span {{ $attributes->class('inline-flex tracking-wide text-amber-500') }} role="img" aria-label="{{ __('community.reviews.rating_label', ['value' => \Illuminate\Support\Number::format((float) $value, 1)]) }}">
    @for ($i = 1; $i <= 5; $i++)<span @class(['text-zinc-300 dark:text-zinc-600' => $i > $filled]) aria-hidden="true">★</span>@endfor
</span>
