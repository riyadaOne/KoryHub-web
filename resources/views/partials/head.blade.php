<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? __($title).' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

{{-- Partage (W05, F-RCH-16) : description et Open Graph des pages publiques. --}}
@php($pageTitle = filled($title ?? null) ? __($title).' - '.config('app.name') : config('app.name'))
@php($pageDescription = \Illuminate\Support\Str::limit((string) ($description ?? __('common.home_lead')), 200))
<meta name="description" content="{{ $pageDescription }}" />
<meta property="og:site_name" content="{{ config('app.name') }}" />
<meta property="og:type" content="website" />
<meta property="og:title" content="{{ $pageTitle }}" />
<meta property="og:description" content="{{ $pageDescription }}" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
