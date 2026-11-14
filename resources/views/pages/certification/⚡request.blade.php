<?php

use App\Models\Module;
use App\Models\ModuleVersion;
use App\Services\Certification\CertificationRefused;
use App\Services\Certification\CertificationService;
use App\Services\Certification\PreliminaryChecks;
use App\Services\Modules\ModuleFinder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * S-35 Demande de certification (F-MOD-01, F-MOD-02, F-MOD-17) : auteur seul, module public,
 * e-mail vérifié ; contrôles préliminaires affichés à l'ouverture ; « Demander » actif seulement
 * s'ils passent tous ; ensuite, renvoi vers le dossier (S-36).
 */
new #[Title('certification.request.title')] class extends Component {
    #[Locked]
    public string $author = '';

    #[Locked]
    public string $moduleName = '';

    #[Locked]
    public string $number = '';

    public ?string $error = null;

    public function mount(string $author, string $module, string $number): void
    {
        [$this->author, $this->moduleName, $this->number] = [$author, $module, $number];
        abort_unless(app(CertificationService::class)->canRequest($this->module, Auth::user()), 403);
        $this->version;
    }

    #[Computed]
    public function module(): Module
    {
        return app(ModuleFinder::class)->find($this->author, $this->moduleName, Auth::user());
    }

    #[Computed]
    public function version(): ModuleVersion
    {
        return app(ModuleFinder::class)->version($this->module, $this->number, Auth::user());
    }

    #[Computed]
    public function checks(): array
    {
        return app(PreliminaryChecks::class)->run($this->version);
    }

    public function submit(CertificationService $certifications)
    {
        try {
            $certification = $certifications->request($this->version, Auth::user());
        } catch (CertificationRefused $refused) {
            $this->error = $refused->getMessage();
            unset($this->checks);

            return null;
        }

        return $this->redirectRoute('certifications.show', ['certification' => $certification->id], navigate: true);
    }
}; ?>

<section class="grid gap-5 py-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('certification.request.title') }}</flux:heading>
        <div class="mt-1 font-mono text-sm text-zinc-500 dark:text-zinc-400" dir="ltr">{{ $this->module->fullName() }} {{ $this->version->version }}</div>
    </div>

    <div class="grid max-w-2xl gap-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" level="2">{{ __('certification.checks.title') }}</flux:heading>
        <x-certification.checks :checks="$this->checks" />

        @php($passed = \App\Services\Certification\PreliminaryChecks::allPassed($this->checks))
        @if ($passed)
            <flux:text>{{ __('certification.request.explain', ['version' => $this->version->version]) }}</flux:text>
        @else
            <x-warning-banner :heading="trans_choice('certification.request.to_fix', collect($this->checks)->where('passed', false)->count(), ['count' => collect($this->checks)->where('passed', false)->count()])" data-test="checks-failed">
                {{ __('certification.request.to_fix_text') }}
            </x-warning-banner>
        @endif

        @if ($error)
            <flux:callout icon="exclamation-triangle" color="red"><flux:callout.heading>{{ $error }}</flux:callout.heading></flux:callout>
        @endif

        <div class="flex flex-wrap gap-2">
            <flux:button variant="primary" wire:click="submit" :disabled="! $passed" data-test="request-certification">{{ __('certification.request.submit') }}</flux:button>
            <flux:button :href="route('modules.version', ['author' => $this->module->author->handle, 'module' => $this->module->name, 'number' => $this->version->version])" wire:navigate>{{ __('certification.request.cancel') }}</flux:button>
        </div>
    </div>
</section>
