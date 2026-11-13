{{-- Espace connecté (UXUI §5.2) : même en-tête que les pages publiques. --}}
<x-layouts::public :title="$title ?? null" :description="$description ?? null">
    {{ $slot }}
</x-layouts::public>
