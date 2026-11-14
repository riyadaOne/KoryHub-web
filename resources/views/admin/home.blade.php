{{-- S-60 Tableau de bord de l'administration, version de départ (W06) : dossiers de certification à traiter ; complété en W07 et W08. --}}
@php($open = \App\Models\Certification::query()->whereIn('status', \App\Enums\CertificationStatus::open()))
@php($oldest = (clone $open)->with(['module.author', 'version'])->oldest('requested_at')->first())
<x-layouts::admin :title="__('admin.title')">
    <div class="grid gap-6">
        <flux:heading size="xl" level="1">{{ __('common.dashboard') }}</flux:heading>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" data-test="certifications-card">
                <flux:heading>{{ trans_choice('certification.admin.to_review', (clone $open)->count(), ['count' => (clone $open)->count()]) }}</flux:heading>
                <flux:text>{{ __('certification.admin.to_review_detail', ['requested' => (clone $open)->where('status', \App\Enums\CertificationStatus::Requested)->count(), 'audit' => (clone $open)->where('status', \App\Enums\CertificationStatus::InAudit)->count()]) }}</flux:text>
                <div><flux:button variant="primary" size="sm" :href="route('admin.certifications.index')" wire:navigate>{{ __('certification.mine.open') }}</flux:button></div>
            </div>
            @if ($oldest)
                <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ __('certification.admin.oldest') }}</flux:heading>
                    <flux:text class="font-mono" dir="ltr">{{ $oldest->module->fullName() }} {{ $oldest->version->version }}</flux:text>
                    <flux:text><x-relative-date :at="$oldest->requested_at" /></flux:text>
                </div>
            @endif
        </div>
    </div>
</x-layouts::admin>
