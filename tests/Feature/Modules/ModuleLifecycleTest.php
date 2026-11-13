<?php

use App\Enums\ModuleStatus;
use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use App\Services\Accounts\AccountService;
use App\Services\Modules\ModuleLifecycle;
use App\Services\Modules\ModuleRuleViolation;
use App\Services\Modules\PublicationError;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Cycle de vie des modules (W03 bloc B, F-REG-13 à F-REG-18) : dépréciation, suppression sous
 * 72 h, réglages historisés, archivage, suppression d'un module privé.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    $this->lifecycle = app(ModuleLifecycle::class);
});

test('déprécier puis annuler ; la dernière version stable reste juste', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    $v11 = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0'));

    $this->lifecycle->deprecate($v11, 'Double débit possible.', $this->author);
    expect($v11->fresh()->status)->toBe(VersionStatus::Deprecated)
        ->and($v11->fresh()->deprecation_message)->toBe('Double débit possible.')
        ->and(Module::sole()->latest_version_id)->toBe($v11->id);

    $this->get('/@moussa/paiement/v/1.1.0')->assertSee('data-test="deprecated-banner"', false);

    $this->lifecycle->undeprecate($v11->fresh(), $this->author);
    expect($v11->fresh()->status)->toBe(VersionStatus::Published);
});

test('S-04 : l\'auteur déprécie avec un message obligatoire, les autres ne voient pas les actions', function () {
    $version = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));

    Livewire::actingAs($this->author)
        ->test('pages::modules.versions', ['author' => 'moussa', 'module' => 'paiement'])
        ->call('ask', 'deprecate', $version->id)
        ->call('deprecate')->assertHasErrors('message')
        ->set('message', 'Passez en 1.1.0.')->call('deprecate')
        ->assertSet('notice', __('modules.versions.deprecated'));

    expect($version->fresh()->status)->toBe(VersionStatus::Deprecated);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::modules.versions', ['author' => 'moussa', 'module' => 'paiement'])
        ->assertDontSee('data-test="undeprecate-1.0.0"', false)
        ->call('ask', 'undeprecate', $version->id)->assertForbidden();
});

test('une version se supprime dans les 72 h, jamais ensuite, et son numéro reste pris', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    $v11 = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0'));

    $this->lifecycle->deleteVersion($v11, $this->author);
    expect($v11->fresh()->trashed())->toBeTrue()
        ->and(Module::sole()->latestVersion->version)->toBe('1.0.0');
    Storage::disk('local')->assertMissing($v11->archive_path);

    expect(fn () => publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0')))
        ->toThrow(PublicationError::class);

    $v12 = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.2.0'));
    $this->travel(73)->hours();
    expect(fn () => $this->lifecycle->deleteVersion($v12->fresh(), $this->author))->toThrow(ModuleRuleViolation::class);
});

test('S-33 : réglages historisés dans l\'audit, étiquettes limitées, réservé à l\'auteur', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    $category = Category::create(['slug' => 'payments', 'position' => 0]);
    $category->translations()->create(['locale' => 'en', 'name' => 'Payments']);

    Livewire::actingAs($this->author)
        ->test('pages::modules.settings', ['author' => 'moussa', 'module' => 'paiement'])
        ->set('description', 'Nouvelle description')
        ->set('category', $category->id)
        ->set('tags', 'mobile-money, offline, laravel')
        ->call('save')
        ->assertSet('notice', __('modules.form.saved'));

    $module = Module::sole();
    expect($module->description)->toBe('Nouvelle description')
        ->and($module->category_id)->toBe($category->id)
        ->and($module->tags->pluck('name')->sort()->values()->all())->toBe(['laravel', 'mobile-money', 'offline'])
        ->and(AuditEvent::where('action', 'module.updated')->exists())->toBeTrue();

    Livewire::actingAs($this->author)
        ->test('pages::modules.settings', ['author' => 'moussa', 'module' => 'paiement'])
        ->set('tags', implode(',', array_map(fn ($i) => "tag{$i}", range(1, 11))))
        ->call('save')->assertHasErrors('tags');

    $this->actingAs(User::factory()->create())->get('/@moussa/paiement/settings')->assertNotFound();
});

test('archiver bloque les nouvelles versions ; désarchiver les rouvre', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    $module = Module::sole();

    $this->lifecycle->archive($module, $this->author);
    expect($module->fresh()->status)->toBe(ModuleStatus::Archived);
    $this->get('/@moussa/paiement')->assertSee('data-test="archived-banner"', false);
    expect(fn () => publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0')))->toThrow(PublicationError::class);

    $this->lifecycle->unarchive($module->fresh(), $this->author);
    publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0'));
});

test('un module privé se supprime en tapant son nom ; un module public non ; le nom n\'est jamais réutilisé', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'), options: ['visibility' => 'private']);
    $version = Module::sole()->versions()->sole();

    Livewire::actingAs($this->author)
        ->test('pages::modules.settings', ['author' => 'moussa', 'module' => 'paiement'])
        ->call('deleteModule', 'paiem')
        ->call('deleteModule', 'paiement')
        ->assertRedirect(route('modules.mine'));

    expect(Module::count())->toBe(0);
    Storage::disk('local')->assertMissing($version->archive_path);
    expect(fn () => publishKory($this->author, TarBuilder::kory('@moussa/paiement')))->toThrow(PublicationError::class);

    publishKory($this->author, TarBuilder::kory('@moussa/wave'), 'wave');
    expect(fn () => $this->lifecycle->deleteModule(Module::sole(), $this->author))->toThrow(ModuleRuleViolation::class);
});

test('la suppression du compte supprime ses modules privés et garde les publics', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/brouillon'), 'brouillon', ['visibility' => 'private']);
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'));

    app(AccountService::class)->delete($this->author);

    expect(Module::pluck('name')->all())->toBe(['paiement'])
        ->and(Module::sole()->visibility)->toBe(Visibility::Public);
});
