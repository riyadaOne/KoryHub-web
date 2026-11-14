<?php

use App\Enums\CertificationStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\VersionStatus;
use App\Exceptions\InvalidTransition;
use App\Models\AuditEvent;
use App\Models\Certification;
use App\Models\ModuleVersion;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Certification\CertificationRefused;
use App\Services\Certification\CertificationService;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Certification, partie développeur (W06.1, F-MOD-01, F-MOD-02, F-MOD-07, F-MOD-08, F-MOD-17) :
 * contrôles préliminaires, demande, réponse à un complément, relance, écrans S-35 et S-36.
 */

const COMPLETE_README = "# Paiement\n\n## Problème résolu\n\nEncaisser sans double débit.\n\n## Pièges évités\n\n1. Clé par commande.\n";

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    $this->admin = User::factory()->create(['handle' => 'achta', 'role' => Role::Admin]);
    $this->service = app(CertificationService::class);
});

function certifiable(User $author, string $version = '1.0.0', array $manifest = [], ?string $readme = COMPLETE_README, string $visibility = 'public'): ModuleVersion
{
    return publishKory($author, TarBuilder::kory('@moussa/paiement', $version, ['src/A.php' => "<?php\nfinal class A {}\n", 'src/B.php' => "<?php\nfinal class B {}\n"], [
        'architecture' => ['components' => [['id' => 'A', 'file' => 'src/A.php', 'kind' => 'class'], ['id' => 'B', 'file' => 'src/B.php', 'kind' => 'class']], 'links' => [['from' => 'A', 'to' => 'B', 'kind' => 'uses']]],
        ...$manifest,
    ], $readme), options: ['visibility' => $visibility]);
}

test('demande acceptée : dossier demandé, contrôles gardés, admins prévenus, audit', function () {
    $version = certifiable($this->author);

    $certification = $this->service->request($version, $this->author);

    expect($certification->status)->toBe(CertificationStatus::Requested)
        ->and($certification->checks()->where('passed', true)->count())->toBe(5)
        ->and(AuditEvent::where('action', 'certification.requested')->exists())->toBeTrue();
    Notification::assertSentTo($this->admin, ModuleNotification::class, fn ($n) => $n->type === NotificationType::AdminCertificationRequested
        && $n->params['certification'] === $certification->id);
    Notification::assertNotSentTo($this->author, ModuleNotification::class, fn ($n) => $n->type === NotificationType::AdminCertificationRequested);
});

test('README en arabe accepté', function () {
    $version = certifiable($this->author, readme: "# دفع\n\n## المشكلة المحلولة\n\nنص.\n\n## المزالق التي تم تجنبها\n\n1. نص.\n");

    expect($this->service->request($version, $this->author)->status)->toBe(CertificationStatus::Requested);
});

test('refus sans dossier : sans architecture, README incomplet, version dépréciée, dossier ouvert', function () {
    $noArchitecture = publishKory($this->author, TarBuilder::kory('@moussa/simple', readme: COMPLETE_README), 'simple');
    $failed = fn (ModuleVersion $v) => collect(rescue(fn () => $this->service->request($v, $this->author), fn ($e) => $e->checks, false))->where('passed', false)->pluck('code')->all();

    expect($failed($noArchitecture))->toBe(['architecture_declared']);
    expect($failed(certifiable($this->author, readme: "# Paiement\n\n## Problème résolu\n\nx\n")))->toBe(['readme_sections']);

    $deprecated = ModuleVersion::where('version', '1.0.0')->whereHas('module', fn ($q) => $q->where('name', 'paiement'))->sole();
    $deprecated->forceFill(['status' => VersionStatus::Deprecated])->save();
    expect($failed($deprecated->fresh()))->toBe(['readme_sections', 'version_published']); // même version, README incomplet

    $ok = certifiable($this->author, '1.1.0');
    $this->service->request($ok, $this->author);
    expect($failed(certifiable($this->author, '1.2.0')))->toBe(['no_open_case'])
        ->and(Certification::count())->toBe(1);
});

test('refus : module d\'un autre, e-mail non vérifié, module privé', function () {
    $version = certifiable($this->author);
    $other = User::factory()->create();
    expect(fn () => $this->service->request($version, $other))->toThrow(CertificationRefused::class);

    $this->author->forceFill(['email_verified_at' => null])->save();
    expect(fn () => $this->service->request($version, $this->author->fresh()))->toThrow(CertificationRefused::class);

    $awa = User::factory()->create(['handle' => 'awa']);
    $private = publishKory($awa, TarBuilder::kory('@awa/brouillon', readme: COMPLETE_README), 'brouillon', ['visibility' => 'private']);
    expect(fn () => $this->service->request($private, $awa))->toThrow(CertificationRefused::class);
});

test('complément : réponse par un texte, puis par une nouvelle version (contrôles relancés) ; hors complément : refus', function () {
    $certification = $this->service->request(certifiable($this->author), $this->author);
    expect(fn () => $this->service->respond($certification, $this->author, 'Trop tôt'))->toThrow(InvalidTransition::class);

    $certification->forceFill(['status' => CertificationStatus::ComplementRequired, 'complement_requested_at' => now()])->save();
    $this->service->respond($certification, $this->author, 'Section complétée.');
    expect($certification->fresh()->status)->toBe(CertificationStatus::InAudit)->and($certification->messages()->count())->toBe(1);

    $certification->forceFill(['status' => CertificationStatus::ComplementRequired])->save();
    $bad = certifiable($this->author, '1.0.1', readme: "# P\n");
    expect(fn () => $this->service->respond($certification->fresh(), $this->author, 'Voici 1.0.1', $bad))->toThrow(CertificationRefused::class);

    $awa = User::factory()->create(['handle' => 'awa']);
    $foreign = publishKory($awa, TarBuilder::kory('@awa/autre', readme: COMPLETE_README), 'autre');
    expect(fn () => $this->service->respond($certification->fresh(), $this->author, 'Autre module', $foreign))->toThrow(CertificationRefused::class);

    $good = certifiable($this->author, '1.0.2');
    $this->service->respond($certification->fresh(), $this->author, 'Voici 1.0.2', $good);
    expect($certification->fresh()->version_id)->toBe($good->id)
        ->and($certification->fresh()->status)->toBe(CertificationStatus::InAudit)
        ->and(AuditEvent::where('action', 'certification.responded')->latest('occurred_at')->first()->changes()->pluck('field')->all())->toContain('version');
});

test('relance après 7 jours sans réponse, une fois par jour', function () {
    $certification = $this->service->request(certifiable($this->author), $this->author);
    $certification->forceFill(['status' => CertificationStatus::ComplementRequired, 'complement_requested_at' => now()->subDays(6)])->save();
    expect($this->service->remindStale())->toBe(0);

    $this->travel(1)->days();
    expect($this->service->remindStale())->toBe(1)->and($this->service->remindStale())->toBe(0);
    $this->travel(1)->days();
    expect($this->service->remindStale())->toBe(1);
    expect(Notification::sent($this->author, ModuleNotification::class)->filter(fn ($n) => $n->type === NotificationType::CertificationComplement))->toHaveCount(2);
});

test('S-35, S-36 et la carte du tableau de bord ; dossier d\'un autre : 404', function () {
    $version = certifiable($this->author);
    $this->actingAs($this->author);

    Livewire::test('pages::certification.request', ['author' => 'moussa', 'module' => 'paiement', 'number' => '1.0.0'])
        ->assertSee(__('certification.checks.architecture_declared'))
        ->call('submit')->assertRedirect();
    $certification = Certification::sole();

    $this->get(route('certifications.index'))->assertOk()->assertSee('@moussa/paiement');
    $certification->forceFill(['status' => CertificationStatus::ComplementRequired])->save();
    $this->get(route('dashboard'))->assertSee(__('certification.dashboard.complement', ['module' => '@moussa/paiement', 'version' => '1.0.0']));

    Livewire::test('pages::certification.show', ['certification' => $certification])
        ->set('body', 'Précisions ajoutées.')->call('respond')->assertHasNoErrors();
    expect($certification->fresh()->status)->toBe(CertificationStatus::InAudit);

    $this->actingAs(User::factory()->create())->get(route('certifications.show', $certification))->assertNotFound();
    $this->get(route('modules.certification', ['author' => 'moussa', 'module' => 'paiement', 'number' => '1.0.0']))->assertForbidden();
});
