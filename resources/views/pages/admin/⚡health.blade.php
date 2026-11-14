<?php

use App\Enums\BackupKind;
use App\Enums\BackupStatus;
use App\Models\BackupRun;
use App\Services\Operations\BackupService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-72 Supervision et sauvegardes (F-ADM-08, F-ADM-09, T-36, T-37), lecture seule : dernier signal
 * du planificateur, dernière sauvegarde, dernier test de restauration, tâches en échec ; liste des
 * sauvegardes. Elles partent par la planification, jamais par un bouton (choix 1 des maquettes W08).
 */
new #[Layout('layouts::admin')] #[Title('admin.health.title')] class extends Component {
    use WithPagination;

    #[Computed]
    public function runs()
    {
        return BackupRun::query()->latest('started_at')->paginate(30);
    }

    public function heartbeat(): ?Carbon
    {
        $at = Cache::get(BackupService::HEARTBEAT_KEY);

        return $at ? Carbon::parse($at) : null;
    }

    public function lastBackup(): ?BackupRun
    {
        return BackupRun::query()->whereIn('kind', [BackupKind::Daily, BackupKind::Monthly])->latest('started_at')->first();
    }

    public function lastRestore(): ?BackupRun
    {
        return BackupRun::query()->where('kind', BackupKind::RestoreTest)->latest('started_at')->first();
    }

    public function failedJobs(): int
    {
        return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
    }

    public function heartbeatConfigured(): bool
    {
        return (string) config('koryhub.healthchecks.heartbeat') !== '';
    }
}; ?>

@php($beat = $this->heartbeat())
@php($backup = $this->lastBackup())
@php($restore = $this->lastRestore())
@php($failed = $this->failedJobs())
<div class="grid gap-5">
    <flux:heading size="xl" level="1">{{ __('admin.health.title') }}</flux:heading>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-test="health-tiles">
        @foreach ([
            ['scheduler', __('admin.health.scheduler'), $beat !== null && $beat->gt(now()->subMinutes(15)), $beat ? __('admin.health.last_signal', ['when' => $beat->diffForHumans()]) : __('admin.health.no_signal')],
            ['backup', __('admin.health.backup'), $backup?->status === BackupStatus::Success, $backup ? $backup->status->label().' · '.$backup->started_at->diffForHumans() : __('admin.health.none')],
            ['restore', __('admin.health.restore'), $restore?->status === BackupStatus::Success, $restore ? $restore->status->label().' · '.$restore->started_at->diffForHumans() : __('admin.health.none')],
            ['queue', __('admin.health.queue'), $failed === 0, trans_choice('admin.health.failed_jobs', $failed, ['count' => $failed])],
        ] as [$key, $label, $ok, $detail])
            <div @class(['grid gap-1 rounded-lg border bg-white p-4 dark:bg-zinc-900', 'border-zinc-200 dark:border-zinc-700' => $ok, 'border-red-600' => ! $ok]) data-test="tile-{{ $key }}" data-ok="{{ $ok ? 'yes' : 'no' }}">
                <div class="flex items-center gap-2 font-semibold">
                    <span @class(['size-2.5 rounded-full', 'bg-green-600' => $ok, 'bg-red-600' => ! $ok])></span>{{ $label }}
                </div>
                <flux:text class="text-sm">{{ $detail }}</flux:text>
            </div>
        @endforeach
    </div>
    @unless ($this->heartbeatConfigured())
        <flux:text class="text-sm" data-test="healthchecks-off">{{ __('admin.health.healthchecks_off') }}</flux:text>
    @endunless

    @if ($this->runs->isEmpty())
        <x-empty-state icon="circle-stack" :title="__('admin.health.empty')" data-test="runs-empty" />
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm" data-test="backup-runs">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('admin.health.type') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.mine.status') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('admin.health.started') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('admin.health.duration') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('admin.health.size') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('admin.health.note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->runs as $run)
                        <tr class="border-t border-zinc-200 align-top dark:border-zinc-700" wire:key="{{ $run->id }}">
                            <td class="whitespace-nowrap px-3 py-2">{{ $run->kind->label() }}</td>
                            <td class="px-3 py-2"><x-status-badge :status="$run->status" /></td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $run->started_at->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $run->duration() !== null ? __('admin.health.seconds', ['count' => $run->duration()]) : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $run->size === null ? '—' : ($run->size < 1048576 ? __('admin.health.size_kb', ['size' => \Illuminate\Support\Number::format($run->size / 1024, 1)]) : __('admin.health.size_mb', ['size' => \Illuminate\Support\Number::format($run->size / 1048576, 1)])) }}</td>
                            <td class="px-3 py-2">{{ $run->note ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $this->runs->links() }}</div>
    @endif
</div>
