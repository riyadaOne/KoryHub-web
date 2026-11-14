{{-- S-60 Tableau de bord de l'administration : signalements (W07) et dossiers de certification (W06) à traiter ; complété en W08. --}}
@php($open = \App\Models\Certification::query()->whereIn('status', \App\Enums\CertificationStatus::open()))
@php($oldest = (clone $open)->with(['module.author', 'version'])->oldest('requested_at')->first())
@php($reports = \App\Models\Report::query()->whereIn('status', \App\Enums\ReportStatus::open()))
@php($staleReports = (clone $reports)->where('created_at', '<=', now()->subHours((int) config('koryhub.moderation.stale_hours', 48)))->count())
@php($multiple = (clone $reports)->select('target_type', 'target_id')->selectRaw('COUNT(*) as total')->groupBy('target_type', 'target_id')->havingRaw('COUNT(*) >= ?', [(int) config('koryhub.moderation.multiple_threshold', 3)])->orderByDesc('total')->first())
@php($multipleReport = $multiple ? \App\Models\Report::query()->whereIn('status', \App\Enums\ReportStatus::open())->where('target_type', $multiple->target_type)->where('target_id', $multiple->target_id)->oldest()->first() : null)
<x-layouts::admin :title="__('admin.title')">
    <div class="grid gap-6">
        <flux:heading size="xl" level="1">{{ __('common.dashboard') }}</flux:heading>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" data-test="reports-card">
                <flux:heading>{{ trans_choice('moderation.admin.open_reports', (clone $reports)->count(), ['count' => (clone $reports)->count()]) }}</flux:heading>
                <flux:text>{{ trans_choice('moderation.admin.stale_reports', $staleReports, ['count' => $staleReports]) }}</flux:text>
                <div><flux:button variant="primary" size="sm" :href="route('admin.reports.index')" wire:navigate>{{ __('certification.mine.open') }}</flux:button></div>
            </div>
            @if ($multipleReport)
                <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" data-test="multiple-card">
                    <flux:heading>{{ __('moderation.admin.multiple') }}</flux:heading>
                    <flux:text><a href="{{ route('admin.reports.show', $multipleReport) }}" wire:navigate>{{ $multipleReport->targetLabel() }}</a> · {{ trans_choice('moderation.admin.on_target', $multiple->total, ['count' => $multiple->total]) }}</flux:text>
                </div>
            @endif
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
