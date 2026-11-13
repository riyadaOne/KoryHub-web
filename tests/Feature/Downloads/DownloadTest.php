<?php

use App\Enums\TokenOrigin;
use App\Enums\VersionStatus;
use App\Models\DownloadStat;
use App\Models\ModuleDownload;
use App\Models\User;
use App\Services\Cli\CliTokenService;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Téléchargement par le CLI (W04 bloc A, F-RCH-14, F-RCH-15, F-INT-08, T-25, T-28, T-31) :
 * informations ouvertes sans compte, lien signé, compteurs, limites, lien privé avec le compte.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    RateLimiter::clear('downloads:ip:127.0.0.1');
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    $this->api = 'http://'.config('koryhub.domains.api').'/v1/modules/moussa/paiement';
});

function cliToken(User $user): string
{
    [$token] = app(CliTokenService::class)->create($user, 'kory · Windows', 90, TokenOrigin::CliLogin);

    return $token;
}

test('sans compte : informations, détail d\'une version, lien signé et compteurs', function () {
    $v = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/PaymentService.php' => "<?php\n"], [
        'requires' => [['ecosystem' => 'composer', 'name' => 'php', 'version' => '>=8.3']],
    ]));

    $this->getJson($this->api)->assertOk()
        ->assertJsonPath('latest_version', '1.0.0')
        ->assertJsonPath('certified', false)
        ->assertJsonPath('versions.0.sha256', $v->archive_sha256);

    $this->getJson($this->api.'/versions/1.0.0')->assertOk()
        ->assertJsonPath('requires.0', ['ecosystem' => 'composer', 'name' => 'php', 'version' => '>=8.3'])
        ->assertJsonPath('files.0.path', 'README.md')
        ->assertJsonPath('license', 'MIT');

    $response = $this->get($this->api.'/versions/1.0.0/download')->assertRedirect();
    expect($response->headers->get('Location'))->toContain($v->archive_path)->toContain('expiration=');

    $v->refresh();
    expect($v->download_count)->toBe(1)
        ->and($v->first_downloaded_at)->not->toBeNull()
        ->and($v->module->download_count)->toBe(1)
        ->and($v->module->third_party_downloaded_at)->not->toBeNull()
        ->and(DownloadStat::sole()->count)->toBe(1)
        ->and(ModuleDownload::count())->toBe(0);

    $this->get($this->api.'/versions/1.0.0/download')->assertRedirect();
    expect(DownloadStat::sole()->count)->toBe(2);
});

test('connecté : lien privé avec le compte ; l\'auteur ne compte pas comme tiers', function () {
    $v = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));

    $this->withToken(cliToken($this->author))->get($this->api.'/versions/1.0.0/download')->assertRedirect();
    expect($v->fresh()->first_downloaded_at)->not->toBeNull()
        ->and($v->module->fresh()->third_party_downloaded_at)->toBeNull();

    $hassan = User::factory()->create(['handle' => 'hassan']);
    $this->app['auth']->forgetGuards();
    $token = cliToken($hassan);
    $this->withToken($token)->get($this->api.'/versions/1.0.0/download')->assertRedirect();
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->get($this->api.'/versions/1.0.0/download')->assertRedirect();

    $link = ModuleDownload::where('user_id', $hassan->id)->sole();
    expect($link->count)->toBe(2)
        ->and($link->version_id)->toBe($v->id)
        ->and($v->module->fresh()->third_party_downloaded_at)->not->toBeNull();
});

test('module privé d\'un autre : 404 ; son auteur le télécharge ; jeton invalide : 401', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'), options: ['visibility' => 'private']);

    $this->getJson($this->api)->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->get($this->api.'/versions/1.0.0/download', ['Accept' => 'application/json'])->assertNotFound();
    $this->withToken(cliToken($this->author))->get($this->api.'/versions/1.0.0/download')->assertRedirect();

    $this->app['auth']->forgetGuards();
    $this->withToken('kory_inconnu')->getJson($this->api)->assertUnauthorized()->assertJsonPath('code', 'token_invalid');
});

test('version retirée : 410 ; version inconnue : 404', function () {
    $v = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    $v->forceFill(['status' => VersionStatus::Removed])->save();

    $this->getJson($this->api.'/versions/1.0.0/download')
        ->assertStatus(410)->assertJsonPath('code', 'version_removed')
        ->assertJsonPath('message', __('api.errors.version_removed', ['version' => '1.0.0']));
    $this->getJson($this->api.'/versions/9.9.9/download')->assertNotFound();
    expect($v->fresh()->download_count)->toBe(0);
});

test('61e téléchargement sans compte dans l\'heure : 429 avec l\'heure de reprise ; une autre adresse IP passe', function () {
    config(['koryhub.downloads.guest_per_hour' => 3]);
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    RateLimiter::clear('downloads:ip:10.0.0.1');
    RateLimiter::clear('downloads:ip:10.0.0.2');

    foreach (range(1, 3) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->get($this->api.'/versions/1.0.0/download')->assertRedirect();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->getJson($this->api.'/versions/1.0.0/download')
        ->assertStatus(429)
        ->assertJsonPath('code', 'download_limit_guest')
        ->assertJsonPath('message', __('api.errors.download_limit_guest', ['limit' => 3]))
        ->assertJsonStructure(['retry_at'])
        ->assertHeader('Retry-After');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get($this->api.'/versions/1.0.0/download')->assertRedirect();
});

test('audit de kory.lock : à jour, mise à jour, dépréciée, retirée, introuvable ; rien n\'est enregistré', function () {
    $v1 = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    $v11 = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0'));
    $v12 = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.2.0'));
    $v11->forceFill(['status' => VersionStatus::Deprecated, 'deprecation_message' => 'Passez en 1.2.0.'])->save();
    $v1->forceFill(['status' => VersionStatus::Removed])->save();
    publishKory($this->author, TarBuilder::kory('@moussa/caisse'), 'caisse');

    $results = $this->postJson('http://'.config('koryhub.domains.api').'/v1/audit', ['modules' => [
        ['name' => '@moussa/paiement', 'version' => '1.2.0'],
        ['name' => '@moussa/paiement', 'version' => '1.1.0'],
        ['name' => '@moussa/paiement', 'version' => '1.0.0'],
        ['name' => '@moussa/inconnu', 'version' => '1.0.0'],
    ]])->assertOk()->json('results');

    expect(collect($results)->pluck('state')->all())->toBe(['up_to_date', 'deprecated', 'removed', 'not_found'])
        ->and($results[1]['message'])->toBe('Passez en 1.2.0.')
        ->and($results[1]['latest_version'])->toBe('1.2.0')
        ->and($v12->fresh()->download_count)->toBe(0);

    publishKory($this->author, TarBuilder::kory('@moussa/caisse', '1.1.0'), 'caisse');
    $states = $this->postJson('http://'.config('koryhub.domains.api').'/v1/audit', ['modules' => [
        ['name' => '@moussa/caisse', 'version' => '1.0.0'],
        ['name' => '@moussa/caisse', 'version' => '0.9.0'],
    ]])->json('results.*.state');
    expect($states)->toBe(['update_available', 'not_found']);
});
