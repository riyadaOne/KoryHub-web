<?php

use App\Http\Middleware\EnsureCliVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/*
 * Socle de l'API du CLI (TRD §17, T-07, T-11) : /v1/meta, version minimale du CLI,
 * format des erreurs, contrat OpenAPI à jour.
 */

function apiUrl(string $path): string
{
    return 'http://'.config('koryhub.domains.api').'/v1'.$path;
}

test('/v1/meta annonce la version de l\'API et les versions du CLI', function () {
    config(['koryhub.cli.minimum_version' => '0.2.0', 'koryhub.cli.latest_version' => '0.3.1']);

    $this->getJson(apiUrl('/meta'))
        ->assertOk()
        ->assertExactJson([
            'api_version' => 'v1',
            'minimum_cli_version' => '0.2.0',
            'latest_cli_version' => '0.3.1',
        ]);
});

test('un CLI trop ancien reçoit 426 avec un message traduit', function () {
    config(['koryhub.cli.minimum_version' => '0.2.0']);

    $this->withHeaders(['User-Agent' => 'kory/0.1.9 (windows; amd64)', 'Accept-Language' => 'fr'])
        ->getJson(apiUrl('/meta'))
        ->assertStatus(426)
        ->assertExactJson([
            'message' => "Mettez à jour kory : cette version n'est plus acceptée.",
            'code' => 'upgrade_required',
        ]);
});

test('un CLI à jour ou plus récent passe', function (string $version) {
    config(['koryhub.cli.minimum_version' => '0.2.0']);

    $this->withHeader('User-Agent', "kory/{$version} (linux; arm64)")
        ->getJson(apiUrl('/meta'))
        ->assertOk();
})->with(['0.2.0', '0.10.0', '1.0.0']);

test('un client qui n\'est pas le CLI n\'est pas concerné par la version minimale', function () {
    config(['koryhub.cli.minimum_version' => '9.9.9']);

    $this->withHeader('User-Agent', 'Mozilla/5.0')->getJson(apiUrl('/meta'))->assertOk();
});

test('la version du CLI est lue dans son User-Agent', function (string $userAgent, ?string $expected) {
    expect(EnsureCliVersion::cliVersion($userAgent))->toBe($expected);
})->with([
    ['kory/1.2.3 (windows; amd64)', '1.2.3'],
    ['kory/1.2.3', '1.2.3'],
    ['kory/1.2 (linux; amd64)', null],
    ['curl/8.0.1', null],
]);

test('une route inconnue de l\'API répond 404 au format de l\'API, dans la langue demandée', function () {
    $this->withHeader('Accept-Language', 'pt-PT')
        ->getJson(apiUrl('/inexistante'))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Não encontrado.', 'code' => 'not_found']);
});

test('le contrat OpenAPI versionné est à jour', function () {
    $committed = resource_path('openapi/v1.json');
    $fresh = storage_path('framework/testing/openapi-v1.json');
    File::ensureDirectoryExists(dirname($fresh));

    Artisan::call('scramble:export', ['--path' => $fresh]);

    expect(json_decode(File::get($fresh), true))
        ->toBe(json_decode(File::get($committed), true), 'Contrat OpenAPI périmé : lancer php artisan scramble:export.');
});
