<?php

use App\Enums\CertificationStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Exceptions\InvalidTransition;
use App\Http\Middleware\EnsureAdmin;
use App\Models\AuditEvent;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Certification\CertificationRefused;
use App\Services\Certification\CertificationService;
use App\Services\Modules\Secrets\SecretScanner;
use App\Services\Search\ModuleSearch;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Certification, partie admin (W06.2, F-MOD-03 à F-MOD-05, F-MOD-15, F-AUT-22) : accès à
 * l'administration, décisions, transitions interdites, badge sur une version, score, filtre.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    $this->admin = User::factory()->create(['handle' => 'achta', 'role' => Role::Admin]);
    $this->admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    $this->service = app(CertificationService::class);
    $this->adminUrl = 'http://'.config('koryhub.domains.admin');

    $readme = "# Paiement\n\n## Problème résolu\n\nx\n\n## Pièges évités\n\n1. y\n";
    $this->version = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/A.php' => "<?php\nfinal class A {}\n"], [
        'architecture' => ['components' => [['id' => 'A', 'file' => 'src/A.php', 'kind' => 'class']], 'links' => []],
    ], $readme));
    $this->certification = $this->service->request($this->version, $this->author);
});

test('administration : 403 pour un développeur ; admin sans double authentification renvoyé vers la sécurité', function () {
    $this->actingAs($this->author)->get($this->adminUrl.'/')->assertForbidden();

    $noTwoFactor = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($noTwoFactor)->get($this->adminUrl.'/certifications')->assertRedirect(route('security.edit'));
    expect(session(EnsureAdmin::SESSION_FLAG))->toBeTrue();

    $this->actingAs($this->admin)->get($this->adminUrl.'/')->assertOk()->assertSee(trans_choice('certification.admin.to_review', 1, ['count' => 1]));
    $this->get($this->adminUrl.'/certifications')->assertOk()->assertSee('@moussa/paiement');
});

test('prendre, demander un complément, réponse, accorder : badge, score, notifications, audit avec motif', function () {
    $this->service->take($this->certification, $this->admin);
    expect($this->certification->fresh()->assigned_to)->toBe($this->admin->id);

    $this->service->requestComplement($this->certification->fresh(), $this->admin, 'Documentez la file d\'attente.');
    Notification::assertSentTo($this->author, ModuleNotification::class, fn ($n) => $n->type === NotificationType::CertificationComplement);
    expect(AuditEvent::where('subject_id', $this->certification->id)->where('reason', 'Documentez la file d\'attente.')->exists())->toBeTrue();

    $this->service->respond($this->certification->fresh(), $this->author, 'Fait.');
    $scoreBefore = (float) $this->version->module->fresh()->score;
    $this->service->grant($this->certification->fresh(), $this->admin, 'Architecture claire.', 'README complet.');

    $module = $this->version->module->fresh();
    expect($this->certification->fresh()->status)->toBe(CertificationStatus::Granted)
        ->and($this->version->fresh()->isCertified())->toBeTrue()
        ->and((float) $module->score)->toBeGreaterThan($scoreBefore);
    Notification::assertSentTo($this->author, ModuleNotification::class, fn ($n) => $n->type === NotificationType::CertificationGranted);

    $this->get(route('modules.show', ['author' => 'moussa', 'module' => 'paiement']))->assertSee(__('certification.badge'));
    $this->getJson('http://'.config('koryhub.domains.api').'/v1/modules/moussa/paiement')->assertJsonPath('certified', true);
    $this->getJson('http://'.config('koryhub.domains.api').'/v1/search?q=paiement')->assertOk();

    // Une nouvelle version n'hérite pas du badge.
    publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0'));
    $this->getJson('http://'.config('koryhub.domains.api').'/v1/modules/moussa/paiement')->assertJsonPath('certified', false);
});

test('refuser et révoquer : motif obligatoire ; transitions interdites', function () {
    expect(fn () => $this->service->grant($this->certification, $this->admin, 'a', 'b'))->toThrow(InvalidTransition::class);

    $this->service->take($this->certification, $this->admin);
    expect(fn () => $this->service->refuse($this->certification->fresh(), $this->admin, '  '))->toThrow(CertificationRefused::class);
    $this->service->refuse($this->certification->fresh(), $this->admin, 'Pas de gestion des erreurs.');
    expect(fn () => $this->service->grant($this->certification->fresh(), $this->admin, 'a', 'b'))->toThrow(InvalidTransition::class);
    Notification::assertSentTo($this->author, ModuleNotification::class, fn ($n) => $n->type === NotificationType::CertificationRefused && $n->params['reason'] === 'Pas de gestion des erreurs.');

    $second = $this->service->request($this->version, $this->author);
    $this->service->take($second, $this->admin);
    $this->service->grant($second->fresh(), $this->admin, 'a', 'b');
    $this->service->revoke($second->fresh(), $this->admin, 'Faille signalée.');
    expect($second->fresh()->status)->toBe(CertificationStatus::Revoked)
        ->and($this->version->fresh()->isCertified())->toBeFalse()
        ->and(fn () => $this->service->grant($second->fresh(), $this->admin, 'a', 'b'))->toThrow(InvalidTransition::class)
        ->and(fn () => $this->service->take($second->fresh(), $this->author))->toThrow(CertificationRefused::class);
});

test('S-64 : prendre le dossier ; accorder redemande le mot de passe', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.certification', ['certification' => $this->certification])
        ->assertSee(__('certification.checks.architecture_declared'))
        ->call('take')->assertSee(__('certification.admin.taken'))
        ->call('open', 'grant')->assertRedirect(route('password.confirm'));

    session(['auth.password_confirmed_at' => time()]);
    Livewire::test('pages::admin.certification', ['certification' => $this->certification])
        ->call('grant')->assertHasErrors(['architectureComment', 'documentationComment'])->assertHasNoErrors('reason')
        ->set('architectureComment', 'Claire.')->set('documentationComment', 'Complète.')
        ->call('grant')->assertSee(__('certification.admin.granted'));
    expect($this->certification->fresh()->status)->toBe(CertificationStatus::Granted);
});

test('S-64 : après un refus sans motif, le refus motivé n\'affiche plus l\'erreur', function () {
    $this->actingAs($this->admin);
    $this->service->take($this->certification, $this->admin);
    session(['auth.password_confirmed_at' => time()]);

    Livewire::test('pages::admin.certification', ['certification' => $this->certification])
        ->call('refuse')->assertHasErrors('reason')
        ->set('reason', 'Pas de gestion des erreurs.')->call('refuse')
        ->assertHasNoErrors()->assertSee(__('certification.admin.refused'));
});

test('« Certifiés seulement » ne garde que les modules dont la dernière version est certifiée', function () {
    $other = publishKory($this->author, TarBuilder::kory('@moussa/caisse'), 'caisse');
    $this->service->take($this->certification, $this->admin);
    $this->service->grant($this->certification->fresh(), $this->admin, 'a', 'b');

    $names = collect(app(ModuleSearch::class)->search(['certified' => true], 20)->items())->pluck('name')->all();
    expect($names)->toBe(['paiement'])->and(Certification::count())->toBe(1);
});
