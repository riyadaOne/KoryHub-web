<?php

use App\Enums\AccountStatus;
use App\Enums\NotificationType;
use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Enums\ReportTargetType;
use App\Enums\Role;
use App\Enums\TokenOrigin;
use App\Enums\TokenStatus;
use App\Models\AuditEvent;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Cli\CliTokenService;
use App\Services\Moderation\ModerationRefused;
use App\Services\Moderation\ModerationService;
use App\Services\Moderation\ReportService;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Modules publics, comptes et avis côté admin (W07.2, F-RCH-12, F-RCH-13, F-AUT-15, F-ADM-02, T-13) :
 * S-65 à S-69, mise en avant, suspension, réactivation, suppression, révocation d'urgence.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    $this->admin = User::factory()->create(['handle' => 'achta', 'role' => Role::Admin]);
    $this->admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    $this->adminUrl = 'http://'.config('koryhub.domains.admin');
    $this->moderation = app(ModerationService::class);
    $this->version = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/A.php' => "<?php\n"]));
    $this->private = publishKory($this->author, TarBuilder::kory('@moussa/secret', '1.0.0', ['src/A.php' => "<?php\n"]), 'secret', ['visibility' => 'private']);
});

test('S-65 et S-66 : modules publics seulement, module privé en 404, mise en avant journalisée', function () {
    $this->actingAs($this->author)->get($this->adminUrl.'/modules')->assertForbidden();

    $this->actingAs($this->admin)->get($this->adminUrl.'/modules')->assertOk()->assertSee('@moussa/paiement')->assertDontSee('@moussa/secret');
    $this->get($this->adminUrl.'/modules/'.$this->private->module_id)->assertNotFound();
    $this->get($this->adminUrl.'/modules/'.$this->version->module_id)->assertOk()->assertSee('data-test="remove-1.0.0"', false);

    Livewire::actingAs($this->admin)->test('pages::admin.module', ['module' => $this->version->module_id])->call('toggleFeatured');
    expect($this->version->module->fresh()->is_featured)->toBeTrue()
        ->and(AuditEvent::where('action', 'module.featured')->exists())->toBeTrue()
        ->and(fn () => $this->moderation->setFeatured($this->private->module, $this->admin, true))->toThrow(ModerationRefused::class);
});

test('suspension sur S-68 : mot de passe, motif envoyé, jetons refusés mais gardés, modules publics téléchargeables ; réactivation', function () {
    [$plain, $token] = app(CliTokenService::class)->create($this->author, 'kory · Windows', 90, TokenOrigin::CliLogin);

    Livewire::actingAs($this->admin)->test('pages::admin.user', ['user' => $this->author->id])
        ->call('open', 'suspend')->assertRedirect(route('password.confirm'));

    session(['auth.password_confirmed_at' => time()]);
    Livewire::actingAs($this->admin)->test('pages::admin.user', ['user' => $this->author->id])
        ->set('reason', 'Publication répétée de code copié.')->call('suspend')->assertHasNoErrors();

    expect($this->author->fresh()->status)->toBe(AccountStatus::Suspended)
        ->and($token->fresh()->status())->toBe(TokenStatus::Active);
    Notification::assertSentTo($this->author, ModuleNotification::class, fn ($n) => $n->type === NotificationType::AccountSuspended && $n->params['reason'] === 'Publication répétée de code copié.');
    $api = 'http://'.config('koryhub.domains.api').'/v1';
    auth()->forgetGuards();
    $this->withToken($plain)->getJson($api.'/user')->assertForbidden()->assertJsonPath('code', 'account_suspended');
    auth()->forgetGuards();
    $this->withoutToken()->get($api.'/modules/moussa/paiement/versions/1.0.0/download')->assertRedirect();

    $this->moderation->reactivateAccount($this->author->fresh(), $this->admin, 'Licences corrigées.');
    expect($this->author->fresh()->status)->toBe(AccountStatus::Active)
        ->and(fn () => $this->moderation->suspendAccount($this->admin, $this->admin, 'x'))->toThrow(ModerationRefused::class)
        ->and(fn () => $this->moderation->suspendAccount(User::factory()->create(['role' => Role::Admin]), $this->admin, 'x'))->toThrow(ModerationRefused::class);
});

test('suppression depuis un signalement de compte : signalements clos avec l\'action, données effacées', function () {
    $target = User::factory()->create(['handle' => 'wanjiru']);
    $report = app(ReportService::class)->create(User::factory()->create(), ReportTargetType::User, $target->id, ReportReason::Harassment, null);

    session(['auth.password_confirmed_at' => time()]);
    Livewire::actingAs($this->admin)->test('pages::admin.report', ['report' => $report])
        ->assertSee('data-test="suspend"', false)
        ->set('reason', 'Harcèlement répété.')->call('deleteAccount')->assertHasNoErrors();

    expect($target->fresh()->status)->toBe(AccountStatus::Deleted)
        ->and($target->fresh()->email)->toBeNull()
        ->and($report->fresh()->resolution)->toBe(ReportResolution::AccountDeleted);
});

test('révocation d\'urgence d\'un jeton : motif, audit, la valeur n\'est jamais affichée', function () {
    [$plain, $token] = app(CliTokenService::class)->create($this->author, 'Serveur CI', 90, TokenOrigin::CliLogin);

    $this->actingAs($this->admin)->get($this->adminUrl.'/users/'.$this->author->id)->assertOk()->assertSee('Serveur CI')->assertDontSee($plain);
    Livewire::actingAs($this->admin)->test('pages::admin.user', ['user' => $this->author->id])
        ->call('open', 'revoke', $token->id)->set('reason', 'Jeton publié par erreur sur GitHub.')->call('revokeToken')->assertHasNoErrors();

    expect($token->fresh()->status())->toBe(TokenStatus::Revoked)
        ->and(AuditEvent::where('action', 'token.revoked')->where('reason', 'Jeton publié par erreur sur GitHub.')->exists())->toBeTrue();
    $this->get($this->adminUrl.'/users')->assertOk()->assertSee('@moussa');
});

test('S-69 : avis signalés, masquer avec motif, réafficher', function () {
    $review = Review::create(['module_id' => $this->version->module_id, 'user_id' => User::factory()->create()->id, 'rating' => 1, 'comment' => 'Insulte.']);
    app(ReportService::class)->create(User::factory()->create(), ReportTargetType::Review, $review->id, ReportReason::Harassment, null);

    $this->actingAs($this->admin)->get($this->adminUrl.'/reviews?tab=reported')->assertOk()->assertSee('Insulte.');
    Livewire::actingAs($this->admin)->test('pages::admin.reviews')
        ->call('open', 'hide', $review->id)->call('hide')->assertHasErrors('reason')
        ->set('reason', 'Propos injurieux.')->call('hide')->assertHasNoErrors();
    expect($review->fresh()->hidden_at)->not->toBeNull();

    Livewire::actingAs($this->admin)->test('pages::admin.reviews', ['tab' => 'hidden'])
        ->call('open', 'show', $review->id)->set('reason', 'Erreur de modération.')->call('reveal');
    expect($review->fresh()->hidden_at)->toBeNull();
});
