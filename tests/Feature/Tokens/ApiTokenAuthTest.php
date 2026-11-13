<?php

use App\Enums\TokenOrigin;
use App\Enums\TokenStatus;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Cli\CliTokenService;

/*
 * Authentification de l'API par jeton (W02, TRD §7.2) : kory whoami et kory logout.
 */

if (! function_exists('cliApi')) {
    function cliApi(string $path): string
    {
        return 'http://'.config('koryhub.domains.api').'/v1'.$path;
    }
}

/** @return array{0: string, 1: PersonalAccessToken} */
function issueToken(User $user, ?int $days = 90): array
{
    return app(CliTokenService::class)->create($user, 'kory · Windows', $days, TokenOrigin::CliLogin);
}

test('GET /v1/user renvoie le compte et le jeton, et note sa dernière utilisation', function () {
    $user = User::factory()->create(['handle' => 'moussa', 'name' => 'Moussa']);
    [$plain, $token] = issueToken($user, 30);

    $this->withToken($plain)->getJson(cliApi('/user'))
        ->assertOk()
        ->assertExactJson([
            'handle' => 'moussa',
            'name' => 'Moussa',
            'token_name' => 'kory · Windows',
            'token_expires_at' => $token->expires_at->toIso8601ZuluString(),
        ]);

    expect($token->fresh()->last_used_at)->not->toBeNull();
});

test('un jeton révoqué, expiré ou inconnu reçoit 401 token_invalid', function (string $case) {
    $user = User::factory()->create();
    [$plain, $token] = issueToken($user, 7);

    match ($case) {
        'révoqué' => app(CliTokenService::class)->revoke($token),
        'expiré' => $this->travel(8)->days(),
        'inconnu' => $plain = 'kory_inconnu',
    };

    $this->withToken($plain)->getJson(cliApi('/user'))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'token_invalid')
        ->assertJsonPath('message', __('api.errors.token_invalid'));
})->with(['révoqué', 'expiré', 'inconnu']);

test('un compte suspendu reçoit 403 account_suspended sans que son jeton soit révoqué', function () {
    $user = User::factory()->suspended()->create();
    [$plain, $token] = issueToken($user);

    $this->withToken($plain)->getJson(cliApi('/user'))
        ->assertForbidden()
        ->assertJsonPath('code', 'account_suspended');

    expect($token->fresh()->status())->toBe(TokenStatus::Active);
});

test('kory logout révoque le jeton de la requête, qui est refusé ensuite', function () {
    $user = User::factory()->create();
    [$plain, $token] = issueToken($user);
    [, $other] = issueToken($user);

    $this->withToken($plain)->deleteJson(cliApi('/auth/token'))->assertNoContent();

    expect($token->fresh()->status())->toBe(TokenStatus::Revoked)
        ->and($other->fresh()->status())->toBe(TokenStatus::Active);

    $this->app['auth']->forgetGuards();
    $this->withToken($plain)->getJson(cliApi('/user'))->assertUnauthorized();
});

test('le contrat OpenAPI décrit les routes de connexion du CLI', function () {
    $contract = json_decode(file_get_contents(resource_path('openapi/v1.json')), true);

    expect($contract['paths'])->toHaveKeys(['/auth/device', '/auth/device/token', '/auth/token', '/user']);
});
