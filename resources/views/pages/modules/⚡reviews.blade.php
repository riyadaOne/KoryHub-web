<?php

use App\Models\Module;
use App\Models\ModuleDownload;
use App\Models\Review;
use App\Services\Community\ReviewService;
use App\Services\Modules\ModuleFinder;
use App\Services\Modules\ModuleRuleViolation;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * S-07 Avis d'un kory (F-RCH-07, F-RCH-08, F-RCH-17) : moyenne et répartition des notes vérifiées
 * seulement ; avis les plus récents d'abord, 20 par page ; formulaire dans la page pour noter,
 * modifier ou supprimer son avis (confirmation dans l'écran). L'auteur ne note pas son module ;
 * s'il a masqué les avis, ils ne s'affichent plus.
 */
new #[Title('community.reviews.title')] class extends Component {
    use WithPagination;

    #[Locked]
    public string $author = '';

    #[Locked]
    public string $moduleName = '';

    public bool $editing = false;

    public int $rating = 0;

    public string $comment = '';

    public ?string $notice = null;

    public function mount(string $author, string $module): void
    {
        $this->author = $author;
        $this->moduleName = $module;
        $this->module;
    }

    #[Computed]
    public function module(): Module
    {
        return app(ModuleFinder::class)->find($this->author, $this->moduleName, Auth::user());
    }

    #[Computed]
    public function isAuthor(): bool
    {
        return app(ModuleFinder::class)->isAuthor($this->module, Auth::user());
    }

    #[Computed]
    public function mine(): ?Review
    {
        return Auth::check() ? $this->module->reviews()->where('user_id', Auth::id())->first() : null;
    }

    #[Computed]
    public function canReview(): bool
    {
        return app(ReviewService::class)->canReview($this->module, Auth::user());
    }

    /** Comptes dont la note est vérifiée (ont téléchargé le module connectés). */
    #[Computed]
    public function verifiedUsers(): array
    {
        return ModuleDownload::query()->where('module_id', $this->module->id)->distinct()->pluck('user_id')->flip()->all();
    }

    /** Répartition des notes vérifiées, de 5 à 1. */
    #[Computed]
    public function distribution(): array
    {
        $counts = $this->module->reviews()
            ->whereIn('user_id', array_keys($this->verifiedUsers))
            ->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');

        return collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $r) => [$r => (int) ($counts[$r] ?? 0)])->all();
    }

    #[Computed]
    public function reviews()
    {
        return $this->module->reviews()->with('user')->latest()->paginate(20);
    }

    public function edit(): void
    {
        abort_unless($this->canReview, 403);
        $this->rating = $this->mine?->rating ?? 0;
        $this->comment = (string) $this->mine?->comment;
        $this->editing = true;
        $this->resetErrorBag();
    }

    public function save(ReviewService $reviews): void
    {
        $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:'.ReviewService::MAX_COMMENT],
        ], attributes: ['rating' => __('community.reviews.rating'), 'comment' => __('community.reviews.comment')]);

        try {
            $reviews->save($this->module, Auth::user(), $this->rating, $this->comment);
            $this->notice = __('community.reviews.saved');
            $this->editing = false;
        } catch (ModuleRuleViolation $exception) {
            $this->addError('rating', $exception->getMessage());
        }

        $this->refresh();
    }

    public function delete(ReviewService $reviews): void
    {
        abort_if($this->mine === null, 404);
        $reviews->delete($this->mine, Auth::user());
        $this->notice = __('community.reviews.deleted');
        Flux::modal('delete-review')->close();
        $this->editing = false;
        $this->refresh();
    }

    private function refresh(): void
    {
        unset($this->mine, $this->reviews, $this->distribution, $this->verifiedUsers, $this->module);
    }
}; ?>

<div>
    <x-module.header :module="$this->module" tab="reviews" :version="$this->module->latestVersion" :is-author="$this->isAuthor" :files-count="$this->module->latestVersion?->files_count" />

    <div class="grid gap-4 py-6">
        @if ($notice)
            <flux:callout icon="check-circle" color="green" data-test="review-notice"><flux:callout.heading>{{ $notice }}</flux:callout.heading></flux:callout>
        @endif

        @if ($this->module->reviews_hidden)
            <x-empty-state icon="eye-slash" :title="__('community.reviews.hidden')" :description="__('community.reviews.hidden_text')" data-test="reviews-hidden" />
        @else
            @php($verifiedTotal = array_sum($this->distribution))
            <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-5 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="rating-summary">
                <div>
                    <div class="text-3xl font-semibold leading-none">{{ $this->module->rating_avg !== null ? \Illuminate\Support\Number::format($this->module->rating_avg, 1) : '—' }}</div>
                    <x-rating :value="$this->module->rating_avg ?? 0" class="mt-1" />
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice('community.reviews.verified_count', $verifiedTotal, ['count' => $verifiedTotal]) }}</div>
                </div>
                <div class="grid gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    @foreach ($this->distribution as $stars => $total)
                        <div class="grid grid-cols-[14px_minmax(0,1fr)_28px] items-center gap-2">
                            <span>{{ $stars }}</span>
                            <span class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800"><span class="block h-full bg-amber-500" style="width: {{ $verifiedTotal ? round($total * 100 / $verifiedTotal) : 0 }}%"></span></span>
                            <span class="text-end">{{ $total }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($editing)
                <form wire:submit="save" class="grid max-w-2xl gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="review-form">
                    <flux:heading>{{ __('community.reviews.form_title', ['module' => $this->module->fullName()]) }}</flux:heading>
                    <flux:field>
                        <flux:label>{{ __('community.reviews.rating') }}</flux:label>
                        <div class="flex gap-1 text-2xl" role="radiogroup" aria-label="{{ __('community.reviews.rating') }}">
                            @foreach ([1, 2, 3, 4, 5] as $value)
                                <button type="button" wire:click="$set('rating', {{ $value }})" role="radio" aria-checked="{{ $rating === $value ? 'true' : 'false' }}" aria-label="{{ trans_choice('community.reviews.stars', $value, ['count' => $value]) }}" @class(['text-amber-500' => $rating >= $value, 'text-zinc-300 dark:text-zinc-600' => $rating < $value]) data-test="star-{{ $value }}">★</button>
                            @endforeach
                        </div>
                        <flux:error name="rating" />
                    </flux:field>
                    <flux:textarea wire:model="comment" :label="__('community.reviews.comment')" rows="4" data-test="review-comment" />
                    @if (isset($this->verifiedUsers[auth()->id()]))
                        <flux:text>{{ __('community.reviews.will_be_verified') }}</flux:text>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary" data-test="save-review">{{ $this->mine ? __('community.reviews.update') : __('community.reviews.publish') }}</flux:button>
                        <flux:button wire:click="$set('editing', false)">{{ __('community.reviews.cancel') }}</flux:button>
                    </div>
                </form>
            @elseif ($this->canReview)
                <div class="flex flex-wrap gap-2">
                    <flux:button variant="primary" wire:click="edit" data-test="write-review">{{ $this->mine ? __('community.reviews.edit_mine') : __('community.reviews.write') }}</flux:button>
                    @if ($this->mine)
                        <flux:modal.trigger name="delete-review">
                            <flux:button variant="danger" data-test="delete-review">{{ __('community.reviews.delete_mine') }}</flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
                <x-confirm-step name="delete-review" :heading="__('community.reviews.delete_title')">
                    {{ __('community.reviews.delete_text') }}
                    <x-slot:action>
                        <flux:button variant="danger" wire:click="delete" data-test="confirm-delete-review">{{ __('community.reviews.delete_mine') }}</flux:button>
                    </x-slot:action>
                </x-confirm-step>
            @elseif (! auth()->check())
                <flux:text><flux:link :href="route('login')">{{ __('community.reviews.login') }}</flux:link></flux:text>
            @endif

            @if ($this->reviews->isEmpty())
                <x-empty-state icon="chat-bubble-left-right" :title="__('community.reviews.empty')" data-test="reviews-empty" />
            @else
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700" data-test="reviews-list">
                    @foreach ($this->reviews as $review)
                        <article class="grid gap-1.5 py-3.5" wire:key="review-{{ $review->id }}">
                            <div class="flex flex-wrap items-center gap-2.5 text-sm">
                                <a href="{{ route('profiles.show', $review->user->handle) }}" class="font-mono font-semibold" dir="ltr" wire:navigate>{{ '@'.$review->user->handle }}</a>
                                @if (isset($this->verifiedUsers[$review->user_id]))
                                    <flux:badge size="sm" color="green" data-test="verified">{{ __('community.reviews.verified') }}</flux:badge>
                                @endif
                                <x-rating :value="$review->rating" />
                                <span class="text-xs text-zinc-500 dark:text-zinc-400"><x-relative-date :at="$review->created_at" />@if ($review->wasEdited()) · {{ __('community.reviews.edited') }}@endif</span>
                            </div>
                            @if ($review->comment)
                                <p class="m-0 whitespace-pre-line text-sm">{{ $review->comment }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
                <div>{{ $this->reviews->links() }}</div>
            @endif
        @endif
    </div>
</div>
