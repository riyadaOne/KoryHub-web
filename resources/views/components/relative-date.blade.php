{{--
    Date relative (« il y a 3 jours »), date exacte au survol, dans le fuseau du navigateur (UXUI §6.2, T-33).
    Le serveur écrit la date en UTC ; resources/js/app.js la reformate dans la langue de la page.
--}}
@props(['at'])

@php
    $date = \Illuminate\Support\Carbon::parse($at)->utc();
@endphp

<time datetime="{{ $date->toIso8601ZuluString() }}" data-relative-date {{ $attributes }}>{{ $date->format('Y-m-d H:i') }} UTC</time>
