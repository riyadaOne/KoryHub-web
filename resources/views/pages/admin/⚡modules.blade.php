<?php

use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Enums\Visibility;
use App\Models\Module;
use App\Models\Report;
use App\Services\Moderation\ModerationRefused;
use App\Services\Moderation\ModerationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-65 Modules publics (F-RCH-12, T-13) : modules publics seulement, jamais un module privé ;
 * recherche par nom ; signalements ouverts ; case « Mis en avant » (décision humaine).
 */
new #[Layout('layouts::admin')] #[Title('moderation.admin.modules')] class extends Component {
    use WithPagination;

    #[Url]
    public string $q = '';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function modules()
    {
        return Module::query()->where('visibility', Visibility::Public)
            ->when(trim($this->q) !== '', fn ($query) => $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->q)).'%'))
            ->with(['author', 'latestVersion.grantedCertification'])
            ->orderByDesc('is_featured')->orderBy('name')
            ->paginate(30);
    }

    /** Signalements ouverts sur le module ou l'une de ses versions. */
    public function openReports(Module $module): int
    {
        return Report::query()->whereIn('status', ReportStatus::open())
            ->where(fn ($q) => $q->where(fn ($m) => $m->where('target_type', ReportTargetType::Module)->where('target_id', $module->id))
                ->orWhere(fn ($v) => $v->where('target_type', ReportTargetType::Version)->whereIn('target_id', $module->versions()->select('id'))))
            ->count();
    }

    public function toggleFeatured(string $moduleId, ModerationService $moderation): void
    {
        $module = Module::query()->where('visibility', Visibility::Public)->findOrFail($moduleId);
        try {
            $moderation->setFeatured($module, Auth::user(), ! $module->is_featured);
        } catch (ModerationRefused) {
        }
        unset($this->modules);
    }
}; ?>

<div class="grid gap-5">
    <flux:heading size="xl" level="1">{{ __('moderation.admin.modules') }}</flux:heading>
    <flux:input wire:model.live.debounce.300ms="q" icon="magnifying-glass" :placeholder="__('moderation.admin.search_module')" class="max-w-sm" data-test="module-search" />

    @if ($this->modules->isEmpty())
        <x-empty-state icon="cube" :title="__('moderation.admin.no_modules')" data-test="admin-modules-empty" />
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm" data-test="admin-modules">
                <thead class="text-xs text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.mine.module') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.latest_version') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('certification.badge') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.reports') }}</th>
                        <th class="px-3 py-2 text-start font-semibold">{{ __('moderation.admin.featured') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->modules as $module)
                        @php($open = $this->openReports($module))
                        <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="{{ $module->id }}">
                            <td class="whitespace-nowrap px-3 py-2 font-mono font-semibold" dir="ltr"><a href="{{ route('admin.modules.show', $module->id) }}" wire:navigate>{{ $module->fullName() }}</a></td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono" dir="ltr">{{ $module->latestVersion?->version ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $module->latestVersion?->isCertified() ? '✓' : '—' }}</td>
                            <td class="px-3 py-2">
                                @if ($open > 0)
                                    <flux:badge size="sm" color="red">{{ $open }}</flux:badge>
                                @else
                                    0
                                @endif
                            </td>
                            <td class="px-3 py-2"><flux:switch :checked="$module->is_featured" wire:click="toggleFeatured('{{ $module->id }}')" :aria-label="__('moderation.admin.featured')" data-test="featured-{{ $module->name }}" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $this->modules->links() }}</div>
    @endif
</div>
