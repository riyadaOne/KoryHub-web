<?php

use App\Enums\ModuleStatus;
use App\Enums\Visibility;
use App\Exceptions\InvalidTransition;
use App\Models\Category;
use App\Models\Module;
use App\Services\Modules\Manifest;
use App\Services\Modules\ModuleFinder;
use App\Services\Modules\ModuleLifecycle;
use App\Services\Modules\ModuleRuleViolation;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * S-33 Réglages du module (F-REG-13, F-REG-14, F-REG-16 à F-REG-18), auteur seul : description,
 * catégorie, étiquettes (10 au plus), visibilité ; archiver ou désarchiver ; supprimer un module
 * privé en tapant son nom. Changements historisés dans l'audit.
 */
new #[Title('modules.settings')] class extends Component {
    #[Locked]
    public string $author = '';

    #[Locked]
    public string $moduleName = '';

    public string $description = '';

    public string $category = '';

    public string $tags = '';

    public string $visibility = 'private';

    public ?string $notice = null;

    public bool $noticeIsError = false;

    public function mount(string $author, string $module): void
    {
        $this->author = $author;
        $this->moduleName = $module;
        abort_unless(app(ModuleFinder::class)->isAuthor($this->module, Auth::user()), 404);

        $this->description = (string) $this->module->description;
        $this->category = (string) $this->module->category_id;
        $this->tags = $this->module->tags->pluck('name')->implode(', ');
        $this->visibility = $this->module->visibility->value;
    }

    #[Computed]
    public function module(): Module
    {
        return app(ModuleFinder::class)->find($this->author, $this->moduleName, Auth::user());
    }

    /** Module public déjà téléchargé par un autre : il ne redevient plus privé (BACKEND-SCHEMA §8). */
    #[Computed]
    public function privateLocked(): bool
    {
        return $this->module->visibility === Visibility::Public && ! app(ModuleLifecycle::class)->canMakePrivate($this->module);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->where('is_active', true)->orderBy('position')->with('translations')->get();
    }

    public function save(ModuleLifecycle $lifecycle): void
    {
        $tags = collect(preg_split('/[\s,]+/', mb_strtolower($this->tags)))->filter()->unique()->values();

        $this->validate([
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'exists:categories,id'],
            'visibility' => ['required', 'in:public,private'],
        ], attributes: ['description' => __('modules.form.description'), 'category' => __('modules.form.category')]);

        if ($tags->count() > Manifest::MAX_TAGS || $tags->contains(fn ($tag) => ! preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $tag))) {
            $this->addError('tags', __('modules.form.tags_invalid', ['max' => Manifest::MAX_TAGS]));

            return;
        }

        try {
            $lifecycle->updateSettings($this->module, [
                'description' => trim($this->description) === '' ? null : trim($this->description),
                'category_id' => $this->category === '' ? null : $this->category,
                'tags' => $tags->all(),
                'visibility' => Visibility::from($this->visibility),
            ], Auth::user());
            [$this->notice, $this->noticeIsError] = [__('modules.form.saved'), false];
        } catch (ModuleRuleViolation $exception) {
            [$this->notice, $this->noticeIsError] = [$exception->getMessage(), true];
        }

        unset($this->module);
    }

    public function toggleArchive(ModuleLifecycle $lifecycle): void
    {
        try {
            $this->module->status === ModuleStatus::Archived
                ? $lifecycle->unarchive($this->module, Auth::user())
                : $lifecycle->archive($this->module, Auth::user());
            [$this->notice, $this->noticeIsError] = [__('modules.form.saved'), false];
        } catch (InvalidTransition $exception) {
            [$this->notice, $this->noticeIsError] = [$exception->getMessage(), true];
        }

        unset($this->module);
        Flux::modal('archive-module')->close();
    }

    public function deleteModule(ModuleLifecycle $lifecycle, string $typed = ''): void
    {
        if ($typed !== $this->module->name) {
            return;
        }

        try {
            $lifecycle->deleteModule($this->module, Auth::user());
        } catch (ModuleRuleViolation $exception) {
            [$this->notice, $this->noticeIsError] = [$exception->getMessage(), true];
            Flux::modal('delete-module')->close();

            return;
        }

        session()->flash('status', __('modules.form.deleted'));
        $this->redirect(route('modules.mine'), navigate: true);
    }
}; ?>

<section class="grid max-w-xl gap-5">
    <div>
        <flux:heading size="xl" level="1" class="font-mono" dir="ltr">{{ '@'.$this->module->author->handle.'/'.$this->module->name }}</flux:heading>
        <flux:subheading>{{ __('modules.settings') }}</flux:subheading>
    </div>

    @if ($notice)
        <flux:callout :variant="$noticeIsError ? 'danger' : 'success'" :icon="$noticeIsError ? 'x-circle' : 'check-circle'" data-test="settings-notice">
            <flux:callout.heading>{{ $notice }}</flux:callout.heading>
        </flux:callout>
    @endif

    <form wire:submit="save" class="grid gap-5">
        <flux:textarea wire:model="description" :label="__('modules.form.description')" rows="3" maxlength="500" data-test="module-description" />

        <flux:select wire:model="category" :label="__('modules.form.category')" data-test="module-category">
            <flux:select.option value="">{{ __('modules.form.no_category') }}</flux:select.option>
            @foreach ($this->categories as $option)
                <flux:select.option :value="$option->id">{{ $option->name() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="tags" :label="__('modules.form.tags')" :description="__('modules.form.tags_hint', ['max' => \App\Services\Modules\Manifest::MAX_TAGS])" dir="ltr" data-test="module-tags" />

        <flux:radio.group wire:model="visibility" :label="__('modules.form.visibility')" variant="segmented" data-test="module-visibility">
            <flux:radio value="private" :disabled="$this->privateLocked">{{ __('modules.visibility.private') }}</flux:radio>
            <flux:radio value="public">{{ __('modules.visibility.public') }}</flux:radio>
        </flux:radio.group>
        <flux:text class="-mt-3" data-test="visibility-hint">{{ $this->privateLocked ? __('modules.form.private_locked') : __('modules.form.private_hint') }}</flux:text>

        <div>
            <flux:button variant="primary" type="submit" data-test="save-module">{{ __('modules.form.save') }}</flux:button>
        </div>
    </form>

    <flux:separator />

    <div class="grid gap-2.5 rounded-lg border border-amber-500 p-4">
        <flux:heading class="text-amber-700! dark:text-amber-400!">{{ $this->module->status === \App\Enums\ModuleStatus::Archived ? __('modules.form.unarchive') : __('modules.form.archive') }}</flux:heading>
        <flux:text>{{ __('modules.form.archive_text') }}</flux:text>
        <div>
            <flux:modal.trigger name="archive-module">
                <flux:button data-test="archive-module-button">{{ $this->module->status === \App\Enums\ModuleStatus::Archived ? __('modules.form.unarchive') : __('modules.form.archive') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($this->module->visibility === \App\Enums\Visibility::Private)
        <div class="grid gap-2.5 rounded-lg border border-red-500 p-4">
            <flux:heading class="text-red-700! dark:text-red-400!">{{ __('modules.form.delete') }}</flux:heading>
            <flux:text>{{ __('modules.form.delete_text') }}</flux:text>
            <div>
                <flux:modal.trigger name="delete-module">
                    <flux:button variant="danger" data-test="delete-module-button">{{ __('modules.form.delete') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>
    @endif

    <x-confirm-step name="archive-module" :heading="$this->module->status === \App\Enums\ModuleStatus::Archived ? __('modules.form.unarchive') : __('modules.form.archive_title')">
        {{ __('modules.form.archive_text') }}
        <x-slot:action>
            <flux:button variant="primary" wire:click="toggleArchive" data-test="confirm-archive">{{ $this->module->status === \App\Enums\ModuleStatus::Archived ? __('modules.form.unarchive') : __('modules.form.archive') }}</flux:button>
        </x-slot:action>
    </x-confirm-step>

    <x-confirm-step name="delete-module" :heading="__('modules.form.delete_title', ['name' => '@'.$this->module->author->handle.'/'.$this->module->name])" :confirm-value="$this->module->name">
        {{ __('modules.form.delete_text') }}
        <x-slot:action>
            <flux:button variant="danger" x-on:click="$wire.deleteModule(typed)" data-test="confirm-delete-module">{{ __('modules.form.delete') }}</flux:button>
        </x-slot:action>
    </x-confirm-step>
</section>
