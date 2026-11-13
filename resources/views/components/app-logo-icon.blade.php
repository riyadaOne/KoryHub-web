{{-- Symbole KoryHub (UXUI §17) : cauri dont la fente est un graphe de trois nœuds. Prend la couleur du texte (currentColor). --}}
@php($maskId = 'koryhub-'.\Illuminate\Support\Str::random(6))
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true" {{ $attributes }}>
    <defs>
        <mask id="{{ $maskId }}" maskUnits="userSpaceOnUse" x="0" y="0" width="64" height="64">
            <g transform="rotate(25 32 32) translate(32 32) scale(0.86) translate(-32 -32)">
                <path fill="#fff" d="M32 4C46.5 4 55 17 55 33C55 49.5 45 60 32 60C19 60 9 49.5 9 33C9 17 17.5 4 32 4Z" />
                <path fill="none" stroke="#000" stroke-width="3" stroke-linecap="round" d="M32 15C29.5 24 34.5 41 32 49" />
                <circle fill="#000" cx="32" cy="15.5" r="4.2" />
                <circle fill="#000" cx="31.6" cy="32" r="4.2" />
                <circle fill="#000" cx="32" cy="48.5" r="4.2" />
            </g>
        </mask>
    </defs>
    <rect width="64" height="64" fill="currentColor" mask="url(#{{ $maskId }})" />
</svg>
