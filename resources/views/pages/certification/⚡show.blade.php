<?php

use App\Enums\CertificationStatus;
use App\Enums\VersionStatus;
use App\Exceptions\InvalidTransition;
use App\Models\Certification;
use App\Services\Certification\CertificationRefused;
use App\Services\Certification\CertificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * S-36, un dossier (F-MOD-03, F-MOD-08) : statut, contrôles, échanges ; pendant un complément,
 * réponse par un texte et, au choix, une nouvelle version publiée du même module (contrôles
 * relancés). Visible par le demandeur seul.
 */
new #[Title('certification.mine.title')] class extends Component {
    #[Locked]
    public string $certificationId = '';

    public string $body = '';

    public string $newVersion = '';

    public ?string $notice = null;

    public array $failedChecks = [];

    public function mount(Certification $certification): void
    {
        abort_unless($certification->requested_by === Auth::id(), 404);
        $this->certificationId = $certification->id;
    }

    #[Computed]
    public function certification(): Certification
    {
        return Certification::query()->with(['module.author', 'version', 'messages.author', 'messages.version'])->findOrFail($this->certificationId);
    }

    /** Dernières vérifications : celles de la version du dossier. */
    #[Computed]
    public function checks()
    {
        return $this->certification->checks()->where('version_id', $this->certification->version_id)->latest('checked_at')->get()->unique('code')->values();
    }

    /** Versions publiées du module, plus récentes que celle du dossier : réponses possibles. */
    #[Computed]
    public function candidates()
    {
        return $this->certification->module->versions()
            ->where('status', VersionStatus::Published)
            ->where('published_at', '>', $this->certification->version->published_at)
            ->latest('published_at')->pluck('version');
    }

    public function respond(CertificationService $certifications): void
    {
        $this->validate(['body' => ['required', 'string', 'max:5000']], attributes: ['body' => __('certification.respond.message')]);
        $version = $this->newVersion === '' ? null
            : $this->certification->module->versions()->where('version', $this->newVersion)->where('status', VersionStatus::Published)->first();

        try {
            $certifications->respond($this->certification, Auth::user(), $this->body, $version);
            [$this->notice, $this->body, $this->newVersion, $this->failedChecks] = [__('certification.respond.sent'), '', '', []];
        } catch (CertificationRefused $refused) {
            $this->addError('body', $refused->getMessage());
            $this->failedChecks = $refused->checks;
        } catch (InvalidTransition $exception) {
            $this->addError('body', $exception->getMessage());
        }

        unset($this->certification, $this->checks, $this->candidates);
    }
}; ?>

<section class="grid gap-5 py-6">
    <div>
        <flux:heading size="xl" level="1" class="font-mono" dir="ltr">{{ $this->certification->module->fullName() }} {{ $this->certification->version->version }}</flux:heading>
        <div class="mt-2"><x-status-badge :status="$this->certification->status" data-test="certification-status" /></div>
    </div>

    @if ($notice)
        <flux:callout icon="check-circle" color="green"><flux:callout.heading>{{ $notice }}</flux:callout.heading></flux:callout>
    @endif

    @if ($this->certification->status === \App\Enums\CertificationStatus::Refused && $this->certification->decision_reason)
        <flux:callout icon="x-circle" color="red"><flux:callout.heading>{{ __('certification.mine.refused_reason') }}</flux:callout.heading><flux:callout.text>{{ $this->certification->decision_reason }}</flux:callout.text></flux:callout>
    @endif

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="grid content-start gap-3">
            <flux:heading size="lg" level="2">{{ __('certification.mine.messages') }}</flux:heading>
            @forelse ($this->certification->messages->sortBy('created_at') as $message)
                <div class="grid gap-1 rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" wire:key="m-{{ $message->id }}">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $message->author_id === $this->certification->requested_by ? '@'.$message->author->handle : __('certification.moderation_team') }} · <x-relative-date :at="$message->created_at" />
                        @if ($message->version)
                            · {{ __('certification.mine.proposed_version', ['version' => $message->version->version]) }}
                        @endif
                    </span>
                    <p class="m-0 whitespace-pre-line">{{ $message->body }}</p>
                </div>
            @empty
                <flux:text>{{ __('certification.mine.no_message') }}</flux:text>
            @endforelse

            @if ($this->certification->status === \App\Enums\CertificationStatus::ComplementRequired)
                <form wire:submit="respond" class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="respond-form">
                    <flux:heading>{{ __('certification.respond.title') }}</flux:heading>
                    <flux:textarea wire:model="body" :label="__('certification.respond.message')" rows="4" data-test="respond-body" />
                    <flux:select wire:model="newVersion" :label="__('certification.respond.version')" data-test="respond-version">
                        <flux:select.option value="">{{ __('certification.respond.no_version') }}</flux:select.option>
                        @foreach ($this->candidates as $candidate)
                            <flux:select.option :value="$candidate">{{ $candidate }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($failedChecks !== [])
                        <x-certification.checks :checks="$failedChecks" />
                    @endif
                    <div><flux:button type="submit" variant="primary" data-test="send-response">{{ __('certification.respond.submit') }}</flux:button></div>
                </form>
            @endif
        </div>

        <aside class="grid content-start gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>{{ __('certification.checks.title') }}</flux:heading>
            <x-certification.checks :checks="$this->checks" />
        </aside>
    </div>
</section>
