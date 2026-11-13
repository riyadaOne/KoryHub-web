<?php

use App\Enums\NotificationType;
use App\Enums\TokenOrigin;
use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Models\AuditEvent;
use App\Models\Module;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Cli\CliTokenService;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Publication d'une version par kory push (W03 bloc A, F-REG-03 à F-REG-12, TRD §13, §22).
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->scanner = new FakeSecretScanner;
    $this->app->instance(SecretScanner::class, $this->scanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    [$this->token] = app(CliTokenService::class)->create($this->author, 'kory · Windows', 90, TokenOrigin::CliLogin);
});

/** Envoie une archive comme kory push. */
function push($test, TarBuilder $archive, array $fields = [], string $module = 'paiement', ?string $token = null)
{
    $path = $archive->save();
    $file = new UploadedFile($path, 'kory.tar.gz', 'application/gzip', null, true);

    return $test->withToken($token ?? $test->token)->post(
        'http://'.config('koryhub.domains.api').'/v1/modules/moussa/'.$module.'/versions',
        array_merge(['archive' => $file, 'sha256' => hash_file('sha256', $path), 'rights_certified' => '1', 'notice_accepted' => '1'], $fields),
        ['Accept' => 'application/json', 'User-Agent' => 'kory/0.3.0 (windows; amd64)'],
    );
}

test('premier push : module privé créé, version publiée, empreintes et archive gardées', function () {
    $response = push($this, TarBuilder::kory('@moussa/paiement'))->assertCreated();

    $module = Module::sole();
    $version = $module->versions()->sole();

    expect($response->json('version'))->toBe('1.0.0')
        ->and($module->visibility)->toBe(Visibility::Private)
        ->and($module->latest_version_id)->toBe($version->id)
        ->and($module->tags->pluck('name')->sort()->values()->all())->toBe(['mobile-money', 'offline'])
        ->and($version->status)->toBe(VersionStatus::Published)
        ->and($version->files_count)->toBe(1)
        ->and($version->readme)->toContain('Problème résolu')
        ->and($this->author->fresh()->hasAcceptedPublicationTerms())->toBeTrue()
        ->and(AuditEvent::where('action', 'version.published')->exists())->toBeTrue();

    // Octets exacts (CRLF conservé) : empreinte du fichier tel qu'envoyé.
    $file = $version->files()->where('path', 'src/PaymentService.php')->sole();
    expect($file->sha256)->toBe(hash('sha256', "<?php\n\nfinal class PaymentService {}\r\n"));

    Storage::disk('local')->assertExists($version->archive_path);
    Notification::assertSentTo($this->author, ModuleNotification::class, fn ($n) => $n->type === NotificationType::VersionPublished);
});

test('le premier push exige la certification des droits et le message de prudence', function () {
    push($this, TarBuilder::kory('@moussa/paiement'), ['rights_certified' => '0'])
        ->assertStatus(422)->assertJsonPath('code', 'terms_required');

    expect(Module::count())->toBe(0);
});

test('un module public choisi au premier push, puis une nouvelle version', function () {
    push($this, TarBuilder::kory('@moussa/paiement'), ['visibility' => 'public'])->assertCreated();
    push($this, TarBuilder::kory('@moussa/paiement', '1.1.0'), ['rights_certified' => '0', 'notice_accepted' => '0'])->assertCreated();

    $module = Module::sole();
    expect($module->visibility)->toBe(Visibility::Public)
        ->and($module->latestVersion->version)->toBe('1.1.0');
});

test('une pré-version ne devient pas la dernière version stable', function () {
    push($this, TarBuilder::kory('@moussa/paiement'))->assertCreated();
    push($this, TarBuilder::kory('@moussa/paiement', '1.1.0-beta.1'))->assertCreated();

    expect(Module::sole()->latestVersion->version)->toBe('1.0.0');
});

test('numéro déjà publié ou inférieur refusé, sans version rejetée', function (string $version, string $code) {
    push($this, TarBuilder::kory('@moussa/paiement', '1.2.0'))->assertCreated();

    push($this, TarBuilder::kory('@moussa/paiement', $version))->assertStatus(409)->assertJsonPath('code', $code);

    expect(Module::sole()->versions()->count())->toBe(1);
})->with([
    'même numéro' => ['1.2.0', 'version_exists'],
    'inférieur' => ['1.1.9', 'version_not_higher'],
    'pré-version inférieure' => ['1.2.0-rc.1', 'version_not_higher'],
]);

test('un secret rejette la version, sans contournement, et le même numéro reste disponible', function () {
    $response = push($this, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/config.php' => "<?php\nSECRET=abc\n"]));

    $response->assertStatus(422)
        ->assertJsonPath('code', 'secret_detected')
        ->assertJsonPath('message', __('api.errors.secret_detected', ['file' => 'src/config.php', 'line' => 2, 'rule' => 'generic-api-key']));

    $rejected = Module::sole()->versions()->sole();
    expect($rejected->status)->toBe(VersionStatus::Rejected)
        ->and($rejected->rejection_code)->toBe('secret_detected')
        ->and($rejected->archive_path)->toBeNull()
        ->and($rejected->rejection_reason)->not->toContain('abc');
    Notification::assertSentTo($this->author, ModuleNotification::class, fn ($n) => $n->type === NotificationType::VersionRejected);

    push($this, TarBuilder::kory('@moussa/paiement', '1.0.0'))->assertCreated();
});

test('un jeton kory_ dans le code est un secret', function () {
    $token = 'kory_'.str_repeat('A', 40).'0123abcd';

    push($this, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/ci.php' => "<?php \$t = '{$token}';\n"]))
        ->assertStatus(422)->assertJsonPath('code', 'secret_detected');
});

test('la recherche de secrets en échec refuse la publication', function () {
    $this->scanner->fail = true;

    push($this, TarBuilder::kory('@moussa/paiement'))->assertStatus(503)->assertJsonPath('code', 'scan_failed');

    expect(Module::first()?->versions()->where('status', VersionStatus::Published)->count() ?? 0)->toBe(0);
});

test('archives piégées refusées', function (Closure $build, string $code) {
    push($this, $build())->assertJsonPath('code', $code);

    expect(Module::first()?->versions()->where('status', VersionStatus::Published)->exists() ?? false)->toBeFalse();
})->with([
    'remontée ..' => [fn () => TarBuilder::kory('@moussa/paiement')->file('../evil.php', 'x'), 'path_forbidden'],
    'chemin absolu' => [fn () => TarBuilder::kory('@moussa/paiement')->file('/etc/passwd', 'x'), 'path_forbidden'],
    'lien symbolique' => [fn () => TarBuilder::kory('@moussa/paiement')->symlink('src/lien', '/etc/passwd'), 'link_forbidden'],
    'binaire' => [fn () => TarBuilder::kory('@moussa/paiement', '1.0.0', ['bin/outil.exe' => "MZ\0\0\x90"]), 'binary_forbidden'],
    'SVG' => [fn () => TarBuilder::kory('@moussa/paiement', '1.0.0', ['docs/schema.svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>']), 'svg_forbidden'],
    'fichier non déclaré' => [fn () => TarBuilder::kory('@moussa/paiement')->file('src/Extra.php', '<?php'), 'files_mismatch'],
    'sans manifeste' => [fn () => (new TarBuilder)->file('README.md', '# x'), 'manifest_missing'],
    'licence inconnue' => [fn () => TarBuilder::kory('@moussa/paiement', manifest: ['license' => 'WTFPL']), 'license_unknown'],
    'mauvais nom' => [fn () => TarBuilder::kory('@awa/paiement'), 'manifest_invalid'],
    'version illisible' => [fn () => TarBuilder::kory('@moussa/paiement', '1.0'), 'version_invalid'],
]);

test('les images de la documentation sont acceptées', function () {
    $png = "\x89PNG\r\n\x1a\n".str_repeat("\0", 32);

    push($this, TarBuilder::kory('@moussa/paiement', '1.0.0', ['docs/flux.png' => $png]))->assertCreated();

    expect(Module::sole()->versions()->sole()->files()->where('path', 'docs/flux.png')->sole()->is_image)->toBeTrue();
});

test('un chemin long passe par un en-tête pax', function () {
    $path = 'src/'.str_repeat('dossier/', 14).'Service.php';

    push($this, TarBuilder::kory('@moussa/paiement', '1.0.0', [$path => '<?php']))->assertCreated();
});

test('bombe de décompression arrêtée par les limites', function () {
    config(['koryhub.publication.max_uncompressed_mb' => 1]);

    push($this, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/gros.txt' => str_repeat('a', 2 * 1024 * 1024)]))
        ->assertStatus(422)->assertJsonPath('code', 'archive_limits');
});

test('empreinte différente, archive trop lourde, autre auteur, e-mail non vérifié', function () {
    push($this, TarBuilder::kory('@moussa/paiement'), ['sha256' => str_repeat('0', 64)])->assertJsonPath('code', 'checksum_mismatch');

    config(['koryhub.publication.max_archive_kb' => 0]);
    push($this, TarBuilder::kory('@moussa/paiement'))->assertStatus(413)->assertJsonPath('code', 'archive_too_large');
    config(['koryhub.publication.max_archive_kb' => 10240]);

    $this->withToken($this->token)->post('http://'.config('koryhub.domains.api').'/v1/modules/awa/paiement/versions', [], ['Accept' => 'application/json'])
        ->assertStatus(422);

    $this->author->forceFill(['email_verified_at' => null])->save();
    $this->app['auth']->forgetGuards();
    push($this, TarBuilder::kory('@moussa/paiement'))->assertForbidden()->assertJsonPath('code', 'email_unverified');
});

test('sans jeton : 401 ; module archivé : 409', function () {
    $this->app['auth']->forgetGuards();
    $this->post('http://'.config('koryhub.domains.api').'/v1/modules/moussa/paiement/versions', [], ['Accept' => 'application/json'])
        ->assertUnauthorized();

    push($this, TarBuilder::kory('@moussa/paiement'))->assertCreated();
    Module::sole()->forceFill(['status' => 'archived', 'archived_at' => now()])->save();

    push($this, TarBuilder::kory('@moussa/paiement', '1.1.0'))->assertStatus(409)->assertJsonPath('code', 'module_archived');
});

test('le nom d\'un module supprimé ne peut pas être réutilisé', function () {
    push($this, TarBuilder::kory('@moussa/paiement'))->assertCreated();
    Module::sole()->delete();

    push($this, TarBuilder::kory('@moussa/paiement', '2.0.0'))->assertStatus(409)->assertJsonPath('code', 'module_name_taken');
});

test('GET /v1/modules : dernière version, et 404 pour le module privé d\'un autre', function () {
    push($this, TarBuilder::kory('@moussa/paiement'))->assertCreated();
    $api = 'http://'.config('koryhub.domains.api').'/v1/modules/moussa/paiement';

    $this->withToken($this->token)->getJson($api)
        ->assertOk()->assertJson(['name' => '@moussa/paiement', 'visibility' => 'private', 'status' => 'active', 'latest_version' => '1.0.0']);

    [$otherToken] = app(CliTokenService::class)->create(User::factory()->create(), 'autre', 90, TokenOrigin::CliLogin);
    $this->app['auth']->forgetGuards();
    $this->withToken($otherToken)->getJson($api)->assertNotFound();
});

test('les préférences coupent l\'e-mail de version publiée mais pas la cloche', function () {
    $this->author->notificationPreferences()->create(['type' => NotificationType::VersionPublished, 'mail' => false, 'database' => true]);
    $notification = new ModuleNotification(NotificationType::VersionPublished, ['module' => '@moussa/paiement', 'version' => '1.0.0']);

    expect($notification->via($this->author->fresh()))->toBe(['database']);
});
