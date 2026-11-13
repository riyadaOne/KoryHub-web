<?php

use App\Enums\DeviceCodeStatus;
use App\Enums\SecurityEventType;
use App\Enums\TokenOrigin;
use App\Models\CliDeviceCode;
use App\Models\User;
use App\Services\Cli\DeviceAuthorizationService;
use App\Services\Cli\DeviceCodeUnavailable;
use Livewire\Livewire;

/*
 * Connexion du CLI par code (W02, TRD §7.3) : API, écran S-26 et transitions des codes.
 */

if (! function_exists('cliApi')) {
    function cliApi(string $path): string
    {
        return 'http://'.config('koryhub.domains.api').'/v1'.$path;
    }
}

const CLI_AGENT = 'kory/0.2.0 (windows; amd64)';

/** Demande un code comme kory login ; renvoie la réponse JSON. */
function requestDeviceCode($test): array
{
    return $test->withHeader('User-Agent', CLI_AGENT)
        ->postJson(cliApi('/auth/device'))
        ->assertOk()
        ->json();
}

function pollDeviceToken($test, string $deviceCode)
{
    return $test->withHeader('User-Agent', CLI_AGENT)
        ->postJson(cliApi('/auth/device/token'), ['device_code' => $deviceCode]);
}

test('kory login reçoit un code ABCD-EFGH valable 15 minutes et l\'adresse de S-26', function () {
    $response = requestDeviceCode($this);

    expect($response['user_code'])->toMatch('/^[BCDFGHJKLMNPQRSTVWXZ]{4}-[BCDFGHJKLMNPQRSTVWXZ]{4}$/')
        ->and($response['expires_in'])->toBe(900)
        ->and($response['interval'])->toBe(5)
        ->and($response['verification_uri'])->toBe(route('device.show'))
        ->and($response['verification_uri_complete'])->toBe(route('device.show').'?code='.$response['user_code']);

    $row = CliDeviceCode::sole();
    expect($row->device_code_hash)->toBe(hash('sha256', $response['device_code']))
        ->and($row->client_info)->toBe('kory 0.2.0 · Windows (amd64)');
});

test('parcours complet : en attente, trop rapide, autorisé, jeton remis une seule fois', function () {
    $user = User::factory()->create(['handle' => 'moussa']);
    $code = requestDeviceCode($this);

    pollDeviceToken($this, $code['device_code'])->assertStatus(400)->assertJsonPath('code', 'authorization_pending');
    pollDeviceToken($this, $code['device_code'])->assertStatus(400)->assertJsonPath('code', 'slow_down');

    $this->actingAs($user);
    Livewire::test('pages::auth.device', ['code' => strtolower(str_replace('-', '', $code['user_code']))])
        ->assertSet('step', 'confirm')
        ->assertSee('kory 0.2.0 · Windows (amd64)')
        ->set('lifetime', '30')
        ->call('approve')
        ->assertSet('step', 'approved');

    $this->travel(6)->seconds();
    $issued = pollDeviceToken($this, $code['device_code'])->assertOk()->json();

    expect($issued['token'])->toStartWith('kory_')
        ->and($issued['handle'])->toBe('moussa')
        ->and($issued['token_name'])->toBe('kory · Windows')
        ->and($issued['expires_at'])->not->toBeNull();

    $token = $user->tokens()->sole();
    expect($token->origin)->toBe(TokenOrigin::CliLogin)
        ->and(CliDeviceCode::sole()->status)->toBe(DeviceCodeStatus::Consumed)
        ->and($user->securityEvents()->where('type', SecurityEventType::CliLogin)->exists())->toBeTrue();

    $this->travel(6)->seconds();
    pollDeviceToken($this, $code['device_code'])->assertStatus(400)->assertJsonPath('code', 'expired_token');
});

test('un code refusé sur S-26 donne access_denied', function () {
    $code = requestDeviceCode($this);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::auth.device', ['code' => $code['user_code']])->call('deny')->assertSet('step', 'denied');

    pollDeviceToken($this, $code['device_code'])->assertStatus(400)->assertJsonPath('code', 'access_denied');
});

test('un code expiré ne peut plus être autorisé et donne expired_token', function () {
    $code = requestDeviceCode($this);
    $this->travel(16)->minutes();
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::auth.device', ['code' => $code['user_code']])
        ->assertSet('step', 'enter')
        ->assertHasErrors('code');

    pollDeviceToken($this, $code['device_code'])->assertStatus(400)->assertJsonPath('code', 'expired_token');
    expect(CliDeviceCode::sole()->status)->toBe(DeviceCodeStatus::Expired);
});

test('un code inconnu donne expired_token, et un code déjà décidé ne change plus', function () {
    pollDeviceToken($this, 'inconnu')->assertStatus(400)->assertJsonPath('code', 'expired_token');

    $user = User::factory()->create();
    [, $row] = app(DeviceAuthorizationService::class)->start(CLI_AGENT);
    app(DeviceAuthorizationService::class)->deny($row, $user);

    app(DeviceAuthorizationService::class)->approve($row, $user, 90);
})->throws(DeviceCodeUnavailable::class);

test('S-26 demande une connexion web d\'abord', function () {
    $this->get(route('device.show', ['code' => 'WDJB-MJHT']))->assertRedirect(route('login'));
});

test('S-26 limite les saisies de code', function () {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test('pages::auth.device');

    foreach (range(1, 10) as $attempt) {
        $page->set('code', 'BCDF-GHJK')->call('check');
    }

    $page->set('code', 'BCDF-GHJK')->call('check')->assertHasErrors('code');
    expect($page->errors()->first('code'))->not->toBe(__('tokens.device_invalid'));
});

test('la saisie du code accepte minuscules et espaces', function () {
    expect(DeviceAuthorizationService::normalize('wdjb mjht'))->toBe('WDJB-MJHT')
        ->and(DeviceAuthorizationService::normalize('WDJB-MJH'))->toBeNull()
        ->and(DeviceAuthorizationService::clientInfo('Mozilla/5.0'))->toBeNull();
});

test('la tâche planifiée expire puis efface les anciens codes', function () {
    requestDeviceCode($this);
    $this->travel(20)->minutes();

    $this->artisan('koryhub:device-codes-prune')->assertSuccessful();
    expect(CliDeviceCode::sole()->status)->toBe(DeviceCodeStatus::Expired);

    $this->travel(8)->days();
    $this->artisan('koryhub:device-codes-prune')->assertSuccessful();
    expect(CliDeviceCode::count())->toBe(0);
});
