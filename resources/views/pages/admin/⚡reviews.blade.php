<?php

use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Enums\Visibility;
use App\Models\Report;
use App\Models\Review;
use App\Services\Moderation\ModerationRefused;
use App\Services\Moderation\ModerationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-69 Avis (F-RCH-13, décision W07 2a) : avis des modules publics ; visibles, signalés, masqués ;
 * masquer ou réafficher sans signalement, motif obligatoire. Un avis signalé se traite aussi sur S-62.
 */
new #[Layout('layouts::admin')] #[Title('moderation.admin.reviews')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'visible';

    #[Locked]
    public ?string $reviewId = null;

    public string $reason = '';

    public ?string $notice = null;

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function reviews()
    {
        $reported = Report::query()->select('target_id')->where('target_type', ReportTargetType::Review)->whereIn('status', ReportStatus::open());

        return Review::query()
            ->whereHas('module', fn ($q) => $q->where('visibility', Visibility::Public))
            ->when($this->tab === 'hidden', fn ($q) => $q->whereNotNull('hidden_at'), fn ($q) => $q->whereNull('hidden_at'))
            ->when($this->tab === 'reported', fn ($q) => $q->whereIn('id', $reported))
            ->with(['user', 'module.author'])
            ->latest()
            ->paginate(30);
    }

    /** @return array<string, true> */
    #[Computed]
    public function reportedIds(): array
    {
        return Report::query()->where('target_type', ReportTargetType::Review)->whereIn('status', ReportStatus::open())->pluck('target_id')->flip()->map(fn () => true)->all();
    }

    public function open(string $action, string $reviewId): void
    {
        $this->resetErrorBag();
        [$this->reason, $this->reviewId] = ['', $reviewId];
        Flux::modal($action.'-review')->show();
    }

    public function hide(ModerationService $moderation): void
    {
        $this->run(fn (Review $review) => $moderation->hideReview($review, Auth::user(), $this->reason), 'hide', 'moderation.admin.hidden_done');
    }

    public function reveal(ModerationService $moderation): void
    {
        $this->run(fn (Review $review) => $moderation->showReview($review, Auth::user(), $this->reason), 'show', 'moderation.admin.shown_done');
    }

    private function run(Closure $action, string $modal, string $done): void
    {
        $this->resetErrorBag();
        $review = Review::query()->whereHas('module', fn ($q) => $q->where('visibility', Visibility::Public))->findOrFail((string) $this->reviewId);
        try {
            $action($review);
            $this->notice = __($done);
            Flux::modal($modal.'-review')->close();
            $this->reason = '';
        } catch (ModerationRefused $exception) {
            $this->addError('reason', $exception->getMessage());
        }
        unset($this->reviews, $this->reportedIds);
    }
}; ?>

<div class="grid gap-5">
    <flux:heading size="xl" level="1">{{ __('moderation.admin.reviews') }}</flux:heading>

    <div class="flex flex-wrap gap-2" role="tablist">
        @foreach ([['visible', __('moderation.admin.tab_visible')], ['reported', __('moderation.admin.tab_reported', ['count' => count($this->reportedIds)])], ['hidden', __('moderation.admin.tab_hidden')]] as [$key, $label])
            <flux:button size="sm" :variant="$tab === $key ? 'primary' : 'outline'" wire:click="$set('tab', '{{ $key }}')" data-test="tab-{{ $key }}">{{ $label }}</flux:button>
        @endforeach
    </div>

    @if ($notice)
        <flux:callout icon="check-circle" color="green" data-test="admin-notice"><flux:callout.heading>{{ $notice }}</flux:callout.heading></flux:callout>
    @endif
    @error('reason')
        <flux:callout icon="exclamation-triangle" color="red" data-test="admin-error"><flux:callout.heading>{{ $message }}</flux:callout.heading></flux:callout>
    @enderror

    @forelse ($this->reviews as $review)
        <article class="grid gap-1.5 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" wire:key="{{ $review->id }}" data-test="admin-review">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-mono font-semibold" dir="ltr">{{ '@'.$review->user->handle }}</span>
                <span class="font-mono text-zinc-500 dark:text-zinc-400" dir="ltr">{{ $review->module->fullName() }}</span>
                <x-rating :value="$review->rating" />
                <span class="text-xs text-zinc-500 dark:text-zinc-400"><x-relative-date :at="$review->created_at" /></span>
                @isset($this->reportedIds[$review->id])
                    <flux:badge size="sm" color="amber">{{ __('moderation.admin.reported') }}</flux:badge>
                @endisset
            </div>
            @if ($review->comment)
                <p class="m-0 whitespace-pre-line text-sm">{{ $review->comment }}</p>
            @endif
            <div>
                @if ($review->hidden_at)
                    <flux:button size="sm" wire:click="open('show', '{{ $review->id }}')" data-test="show-review">{{ __('moderation.admin.show') }}</flux:button>
                @else
                    <flux:button size="sm" variant="danger" wire:click="open('hide', '{{ $review->id }}')" data-test="hide-review">{{ __('moderation.admin.hide') }}</flux:button>
                @endif
            </div>
        </article>
    @empty
        <x-empty-state icon="chat-bubble-left-right" :title="__('community.reviews.empty')" data-test="admin-reviews-empty" />
    @endforelse
    <div>{{ $this->reviews->links() }}</div>

    @foreach ([['hide', __('moderation.admin.hide'), 'danger', 'hide'], ['show', __('moderation.admin.show'), 'primary', 'reveal']] as [$action, $label, $variant, $method])
        <x-confirm-step :name="$action.'-review'" :heading="$label">
            <flux:textarea wire:model="reason" :label="__('certification.admin.reason')" rows="3" data-test="reason-{{ $action }}" />
            <x-slot:action><flux:button :variant="$variant" wire:click="{{ $method }}" data-test="confirm-{{ $action }}">{{ $label }}</flux:button></x-slot:action>
        </x-confirm-step>
    @endforeach
</div>
