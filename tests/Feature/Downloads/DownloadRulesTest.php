<?php

use App\Enums\NotificationType;
use App\Enums\Visibility;
use App\Models\ModuleDownload;
use App\Models\ModuleVersion;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Accounts\AccountService;
use App\Services\Modules\DownloadService;
use App\Services\Modules\ModuleLifecycle;
use App\Services\Modules\ModuleRuleViolation;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Règles liées au premier téléchargement (W04.5, F-REG-13, F-REG-17), alertes aux comptes qui ont
 * téléchargé un module (F-INT-06, F-INT-07) et Mes téléchargements (S-46, F-RCH-14).
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    $this->hassan = User::factory()->create(['handle' => 'hassan']);
    $this->lifecycle = app(ModuleLifecycle::class);
});

function downloadAs(ModuleVersion $version, ?User $user): void
{
    RateLimiter::clear($user ? 'downloads:user:'.$user->id : 'downloads:ip:127.0.0.1');
    app(DownloadService::class)->download($version, $user, '127.0.0.1');
}

test('une version téléchargée, même par son auteur, ne se supprime plus', function () {
    $v = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    expect($this->lifecycle->canDeleteVersion($v))->toBeTrue();

    downloadAs($v, $this->author);

    expect($this->lifecycle->canDeleteVersion($v->fresh()))->toBeFalse();
    expect(fn () => $this->lifecycle->deleteVersion($v->fresh(), $this->author))->toThrow(ModuleRuleViolation::class);
});

test('public → privé refusé après un téléchargement par un tiers ; S-33 le montre', function () {
    $v = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    downloadAs($v, $this->author);
    expect($this->lifecycle->canMakePrivate($v->module->fresh()))->toBeTrue();

    downloadAs($v, null);
    $module = $v->module->fresh();
    expect($this->lifecycle->canMakePrivate($module))->toBeFalse();
    expect(fn () => $this->lifecycle->updateSettings($module, [
        'description' => null, 'category_id' => null, 'tags' => [], 'visibility' => Visibility::Private,
    ], $this->author))->toThrow(ModuleRuleViolation::class);

    $this->actingAs($this->author);
    Livewire::test('pages::modules.settings', ['author' => 'moussa', 'module' => 'paiement'])
        ->assertSee(__('modules.form.private_locked'));
});

test('nouvelle version stable : alerte aux comptes qui ont téléchargé, ni à l\'auteur ni pour une pré-version', function () {
    $v1 = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    downloadAs($v1, $this->hassan);
    downloadAs($v1, $this->author);

    publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0-beta.1'));
    Notification::assertNotSentTo($this->hassan, ModuleNotification::class);

    publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0'));
    Notification::assertSentTo($this->hassan, ModuleNotification::class, fn (ModuleNotification $n) => $n->type === NotificationType::UpdateAvailable
        && $n->params === ['module' => '@moussa/paiement', 'version' => '1.1.0', 'installed' => '1.0.0']);
    Notification::assertNotSentTo($this->author, ModuleNotification::class, fn (ModuleNotification $n) => $n->type === NotificationType::UpdateAvailable);
});

test('version dépréciée : alerte à ceux qui l\'ont téléchargée ; « mise à jour » sans e-mail par défaut', function () {
    $v1 = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    downloadAs($v1, $this->hassan);

    $this->lifecycle->deprecate($v1, 'Double débit possible.', $this->author);
    Notification::assertSentTo($this->hassan, ModuleNotification::class, fn (ModuleNotification $n) => $n->type === NotificationType::VersionDeprecated
        && $n->params['reason'] === 'Double débit possible.');

    expect((new ModuleNotification(NotificationType::UpdateAvailable))->via($this->hassan))->toBe(['database'])
        ->and((new ModuleNotification(NotificationType::VersionDeprecated))->via($this->hassan))->toBe(['mail', 'database']);
});

test('S-46 : mes téléchargements seulement, badge de mise à jour ; effacés avec le compte', function () {
    $v1 = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    downloadAs($v1, $this->hassan);
    $other = publishKory($this->author, TarBuilder::kory('@moussa/caisse'), 'caisse');
    downloadAs($other, $this->author);
    publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0'));

    $this->actingAs($this->hassan)->get(route('downloads.index'))
        ->assertOk()
        ->assertSee('@moussa/paiement')
        ->assertDontSee('@moussa/caisse')
        ->assertSee(__('downloads.newer', ['version' => '1.1.0']));

    $this->actingAs($this->author)->get(route('downloads.index'))->assertOk()->assertSee('@moussa/caisse')->assertDontSee('@moussa/paiement');

    app(AccountService::class)->delete($this->hassan, $this->hassan);
    expect(ModuleDownload::where('user_id', $this->hassan->id)->exists())->toBeFalse();
});

test('S-46 vide : la commande kory login', function () {
    $this->actingAs($this->hassan)->get(route('downloads.index'))
        ->assertOk()->assertSee(__('downloads.empty'))->assertSee('kory login');
});
