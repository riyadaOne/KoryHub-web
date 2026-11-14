<?php

use App\Exceptions\InvalidTransition;
use App\Models\AuditEvent;
use App\Models\Certification;
use App\Services\Certification\CertificationRefused;
use App\Services\Certification\CertificationService;
use App\Support\ArchitectureDiagram;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * S-64 Dossier de certification (F-MOD-03 à F-MOD-05, F-MOD-15), modèle « Dossier » : à gauche
 * README, architecture et fichiers de la version ; à droite contrôles, échanges, frise, décision.
 * Accorder, refuser et révoquer redemandent le mot de passe ; un motif est toujours obligatoire.
 */
new #[Layout('layouts::admin')] #[Title('certification.admin.title')] class extends Component {
    #[Locked]
    public string $certificationId = '';

    public string $tab = 'readme';

    public string $reason = '';

    public string $architectureComment = '';

    public string $documentationComment = '';

    public ?string $notice = null;

    public function mount(Certification $certification): void
    {
        $this->certificationId = $certification->id;
    }

    #[Computed]
    public function certification(): Certification
    {
        return Certification::query()->with(['module.author', 'version.files', 'requester', 'assignee', 'messages.author', 'messages.version'])->findOrFail($this->certificationId);
    }

    #[Computed]
    public function checks()
    {
        return $this->certification->checks()->where('version_id', $this->certification->version_id)->latest('checked_at')->get()->unique('code')->values();
    }

    #[Computed]
    public function timeline()
    {
        return AuditEvent::query()->where('subject_id', $this->certificationId)->with('changes')->orderBy('occurred_at')->get();
    }

    #[Computed]
    public function diagram(): array
    {
        return ArchitectureDiagram::build($this->certification->version);
    }

    public function open(string $action): void
    {
        $this->resetErrorBag();
        $this->reason = '';
        if (in_array($action, ['grant', 'refuse', 'revoke'], true) && ! $this->passwordRecentlyConfirmed()) {
            session()->put('url.intended', route('admin.certifications.show', $this->certificationId));
            $this->redirect(route('password.confirm'));

            return;
        }
        Flux::modal($action.'-certification')->show();
    }

    public function take(CertificationService $service): void
    {
        $this->run(fn () => $service->take($this->certification, Auth::user()), null, 'certification.admin.taken');
    }

    public function complement(CertificationService $service): void
    {
        $this->run(fn () => $service->requestComplement($this->certification, Auth::user(), $this->reason), 'complement', 'certification.admin.complement_sent');
    }

    public function grant(CertificationService $service): void
    {
        abort_unless($this->passwordRecentlyConfirmed(), 403);
        $this->run(fn () => $service->grant($this->certification, Auth::user(), $this->architectureComment, $this->documentationComment), 'grant', 'certification.admin.granted');
    }

    public function refuse(CertificationService $service): void
    {
        abort_unless($this->passwordRecentlyConfirmed(), 403);
        $this->run(fn () => $service->refuse($this->certification, Auth::user(), $this->reason), 'refuse', 'certification.admin.refused');
    }

    public function revoke(CertificationService $service): void
    {
        abort_unless($this->passwordRecentlyConfirmed(), 403);
        $this->run(fn () => $service->revoke($this->certification, Auth::user(), $this->reason), 'revoke', 'certification.admin.revoked');
    }

    private function run(Closure $action, ?string $modal, string $done): void
    {
        try {
            $action();
            $this->notice = __($done);
            if ($modal !== null) {
                Flux::modal($modal.'-certification')->close();
            }
            [$this->reason, $this->architectureComment, $this->documentationComment] = ['', '', ''];
        } catch (CertificationRefused|InvalidTransition $exception) {
            $this->addError('reason', $exception->getMessage());
        }
        unset($this->certification, $this->checks, $this->timeline);
    }

    /** Mot de passe confirmé récemment (même délai que le middleware password.confirm). */
    private function passwordRecentlyConfirmed(): bool
    {
        return time() - (int) session('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 10800);
    }
}; ?>

@php($c = $this->certification)
@php($moduleUrl = route('modules.show', ['author' => $c->module->author->handle, 'module' => $c->module->name]))
<div class="grid gap-5">
    <div>
        <flux:heading size="xl" level="1" class="font-mono" dir="ltr">{{ $c->module->fullName() }} {{ $c->version->version }}</flux:heading>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
            <x-status-badge :status="$c->status" data-test="certification-status" />
            <span>{{ __('certification.admin.assignee') }} : {{ $c->assignee?->displayName() ?? '—' }}</span>
            <flux:link :href="$moduleUrl" external>{{ __('certification.admin.open_page') }}</flux:link>
        </div>
    </div>

    @if ($notice)
        <flux:callout icon="check-circle" color="green" data-test="admin-notice"><flux:callout.heading>{{ $notice }}</flux:callout.heading></flux:callout>
    @endif
    @error('reason')
        <flux:callout icon="exclamation-triangle" color="red"><flux:callout.heading>{{ $message }}</flux:callout.heading></flux:callout>
    @enderror

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="grid min-w-0 content-start gap-3">
            <div class="flex gap-1 border-b border-zinc-200 text-sm dark:border-zinc-700">
                @foreach ([['readme', __('modules.tabs.readme')], ['architecture', __('modules.tabs.architecture')], ['files', __('modules.tabs.files').' · '.$c->version->files->count()]] as [$key, $label])
                    <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['border-b-2 px-3 py-2', 'border-accent font-semibold' => $tab === $key, 'border-transparent text-zinc-500' => $tab !== $key])>{{ $label }}</button>
                @endforeach
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                @if ($tab === 'architecture')
                    <ul class="m-0 grid list-none gap-1 p-0 text-sm">
                        @forelse ($this->diagram['lines'] as $line)
                            <li><span class="font-mono" dir="ltr">{{ $line['from'] }}</span> {{ $line['kind'] }} <span class="font-mono" dir="ltr">{{ $line['to'] }}</span></li>
                        @empty
                            <li>{{ __('modules.architecture.none') }}</li>
                        @endforelse
                    </ul>
                @elseif ($tab === 'files')
                    <ul class="m-0 grid list-none gap-1 p-0 font-mono text-sm" dir="ltr">
                        @foreach ($c->version->files->sortBy('path') as $file)
                            <li><a href="{{ route('modules.files', ['author' => $c->module->author->handle, 'module' => $c->module->name, 'path' => $file->path, 'v' => $c->version->version]) }}" target="_blank" rel="noopener">{{ $file->path }}</a></li>
                        @endforeach
                    </ul>
                @else
                    <article class="readme max-w-[72ch]">{{ \App\Support\CodeView::markdown($c->version->readme) }}</article>
                @endif
            </div>
        </div>

        <aside class="grid content-start gap-3">
            <div class="grid gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('certification.checks.title') }}</flux:heading>
                <x-certification.checks :checks="$this->checks" />
            </div>

            @if ($c->messages->isNotEmpty())
                <div class="grid gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ __('certification.mine.messages') }}</flux:heading>
                    @foreach ($c->messages->sortBy('created_at') as $message)
                        <div class="grid gap-0.5 text-sm" wire:key="m-{{ $message->id }}">
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ '@'.$message->author->handle }} · <x-relative-date :at="$message->created_at" />@if ($message->version) · {{ __('certification.mine.proposed_version', ['version' => $message->version->version]) }}@endif</span>
                            <p class="m-0 whitespace-pre-line">{{ $message->body }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('certification.admin.history') }}</flux:heading>
                <ol class="m-0 grid list-none gap-2 border-s-2 border-zinc-200 ps-3 text-sm dark:border-zinc-700" data-test="timeline">
                    @foreach ($this->timeline as $event)
                        @php($status = \App\Enums\CertificationStatus::tryFrom((string) $event->changes->firstWhere('field', 'status')?->after))
                        <li>
                            {{ $status?->label() ?? $event->action }}
                            <small class="block text-zinc-500 dark:text-zinc-400"><x-relative-date :at="$event->occurred_at" />@if ($event->reason) · {{ $event->reason }}@endif</small>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="flex flex-wrap gap-2" data-test="decisions">
                @if ($c->status === \App\Enums\CertificationStatus::Requested)
                    <flux:button variant="primary" wire:click="take" data-test="take">{{ __('certification.admin.take') }}</flux:button>
                @elseif ($c->status === \App\Enums\CertificationStatus::InAudit)
                    <flux:button variant="primary" wire:click="open('grant')" data-test="grant">{{ __('certification.admin.grant') }}</flux:button>
                    <flux:button wire:click="open('complement')" data-test="complement">{{ __('certification.admin.complement') }}</flux:button>
                    <flux:button variant="danger" wire:click="open('refuse')" data-test="refuse">{{ __('certification.admin.refuse') }}</flux:button>
                @elseif ($c->status === \App\Enums\CertificationStatus::Granted)
                    <flux:button variant="danger" wire:click="open('revoke')" data-test="revoke">{{ __('certification.admin.revoke') }}</flux:button>
                @endif
            </div>
        </aside>
    </div>

    <x-confirm-step name="grant-certification" :heading="__('certification.admin.grant_title', ['version' => $c->version->version])">
        {{ __('certification.admin.grant_text') }}
        <flux:textarea wire:model="architectureComment" :label="__('certification.admin.architecture_comment')" rows="3" class="mt-3" data-test="architecture-comment" />
        <flux:textarea wire:model="documentationComment" :label="__('certification.admin.documentation_comment')" rows="3" class="mt-3" data-test="documentation-comment" />
        <x-slot:action><flux:button variant="primary" wire:click="grant" data-test="confirm-grant">{{ __('certification.admin.grant') }}</flux:button></x-slot:action>
    </x-confirm-step>

    @foreach ([['complement', __('certification.admin.complement'), 'primary'], ['refuse', __('certification.admin.refuse'), 'danger'], ['revoke', __('certification.admin.revoke'), 'danger']] as [$action, $label, $variant])
        <x-confirm-step :name="$action.'-certification'" :heading="$label">
            <flux:textarea wire:model="reason" :label="__('certification.admin.reason')" rows="3" data-test="reason-{{ $action }}" />
            <x-slot:action><flux:button :variant="$variant" wire:click="{{ $action }}" data-test="confirm-{{ $action }}">{{ $label }}</flux:button></x-slot:action>
        </x-confirm-step>
    @endforeach
</div>
