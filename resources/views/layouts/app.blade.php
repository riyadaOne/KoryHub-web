{{-- Espace connecté (UXUI §5.2) : même en-tête que les pages publiques. --}}
<x-layouts::public :title="$title ?? null">
    {{ $slot }}
</x-layouts::public>
