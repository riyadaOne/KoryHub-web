{{-- Accueil provisoire (S-01 complet en W05 : recherche, kory mis en avant, catégories). --}}
<x-layouts::public>
    <section class="mx-auto flex max-w-[720px] flex-col items-center gap-6 py-16 text-center">
        <x-app-logo-icon class="size-20 text-accent" />
        <flux:heading size="xl" level="1" class="text-balance">{{ __('common.home_title') }}</flux:heading>
        <flux:text size="lg" class="text-balance">{{ __('common.home_lead') }}</flux:text>
    </section>
</x-layouts::public>
