<?php

use App\Enums\ReportReason;
use App\Enums\ReportTargetType;
use App\Services\Moderation\ModerationRefused;
use App\Services\Moderation\ReportService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Menu « … » et feuille « Signaler » (F-MOD-09, F-AUT-23, maquettes W07) : sur la fiche, une
 * version, un avis, un profil. Visiteur : mène à la connexion. Jamais sur ce qui appartient au
 * compte connecté. E-mail non vérifié ou signalement déjà ouvert : la feuille l'indique.
 */
new class extends Component {
    #[Locked]
    public string $type = '';

    #[Locked]
    public string $targetId = '';

    public string $reason = 'malware';

    public string $details = '';

    public bool $sent = false;

    #[Computed]
    public function visible(): bool
    {
        if (! Auth::check()) {
            return true;
        }
        try {
            app(ReportService::class)->target(ReportTargetType::from($this->type), $this->targetId, Auth::user());

            return true;
        } catch (ModerationRefused) {
            return false;
        }
    }

    #[Computed]
    public function alreadyOpen(): bool
    {
        return Auth::check() && app(ReportService::class)->openReport(Auth::user(), ReportTargetType::from($this->type), $this->targetId) !== null;
    }

    public function openSheet(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }
        $this->resetErrorBag();
        $this->sent = false;
        Flux::modal($this->modalName())->show();
    }

    public function send(ReportService $reports): void
    {
        $this->validate([
            'reason' => ['required', 'in:'.implode(',', array_column(ReportReason::cases(), 'value'))],
            'details' => ['nullable', 'string', 'max:'.ReportService::MAX_DETAILS],
        ]);

        try {
            $reports->create(Auth::user(), ReportTargetType::from($this->type), $this->targetId, ReportReason::from($this->reason), $this->details);
            $this->sent = true;
            $this->details = '';
            unset($this->alreadyOpen);
        } catch (ModerationRefused $exception) {
            $this->addError('reason', $exception->getMessage());
        }
    }

    public function modalName(): string
    {
        return 'report-'.$this->type.'-'.$this->targetId;
    }
}; ?>

<div>
    @if ($this->visible)
        <flux:dropdown position="bottom" align="end">
            <flux:button size="sm" icon="ellipsis-horizontal" :aria-label="__('moderation.more')" data-test="more-{{ $type }}" />
            <flux:menu>
                <flux:menu.item icon="flag" variant="danger" wire:click="openSheet" data-test="report-{{ $type }}">{{ __('moderation.report') }}</flux:menu.item>
            </flux:menu>
        </flux:dropdown>

        @auth
            <flux:modal :name="$this->modalName()" variant="flyout" class="md:w-[28rem]">
                <div class="grid gap-5">
                    <flux:heading size="lg">{{ $sent ? __('moderation.sheet.sent_title') : __('moderation.report') }}</flux:heading>

                    @if ($sent)
                        <flux:callout icon="check-circle" color="green" data-test="report-sent"><flux:callout.heading>{{ __('moderation.sheet.sent') }}</flux:callout.heading></flux:callout>
                        <flux:text>{{ __('moderation.sheet.sent_text') }} <flux:link :href="route('reports.mine')" wire:navigate>{{ __('moderation.mine.title') }}</flux:link></flux:text>
                    @elseif (! auth()->user()->hasVerifiedEmail())
                        <flux:callout icon="envelope" color="amber" data-test="report-unverified">
                            <flux:callout.heading>{{ __('moderation.errors.email_unverified') }}</flux:callout.heading>
                            <flux:callout.text><flux:link :href="route('verification.notice')">{{ __('moderation.sheet.verify') }}</flux:link></flux:callout.text>
                        </flux:callout>
                    @elseif ($this->alreadyOpen)
                        <flux:callout icon="information-circle" data-test="report-duplicate">
                            <flux:callout.heading>{{ __('moderation.errors.duplicate') }}</flux:callout.heading>
                            <flux:callout.text><flux:link :href="route('reports.mine')" wire:navigate>{{ __('moderation.mine.title') }}</flux:link></flux:callout.text>
                        </flux:callout>
                    @else
                        <flux:radio.group wire:model="reason" :label="__('moderation.sheet.reason')" variant="cards" class="flex-col">
                            @foreach (\App\Enums\ReportReason::cases() as $case)
                                <flux:radio :value="$case->value" :label="$case->label()" :description="$case->description()" data-test="reason-{{ $case->value }}" />
                            @endforeach
                        </flux:radio.group>
                        <flux:textarea wire:model="details" :label="__('moderation.sheet.details')" :badge="__('moderation.sheet.optional')" rows="4" data-test="report-details" />
                        <flux:error name="reason" />
                        <flux:text>{{ __('moderation.sheet.anonymous') }}</flux:text>
                        <div class="flex justify-end gap-2">
                            <flux:modal.close><flux:button variant="ghost">{{ __('common.cancel') }}</flux:button></flux:modal.close>
                            <flux:button variant="danger" wire:click="send" data-test="send-report">{{ __('moderation.sheet.send') }}</flux:button>
                        </div>
                    @endif
                </div>
            </flux:modal>
        @endauth
    @endif
</div>
