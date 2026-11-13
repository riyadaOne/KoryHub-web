<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Routage par domaine (TRD §6) : chaque espace répond sur son domaine, et seulement là.
 */

function koryhubUrl(string $space, string $path = '/'): string
{
    return 'http://'.config("koryhub.domains.{$space}").$path;
}

test('le site principal répond sur son domaine', function () {
    $this->get(koryhubUrl('main'))->assertOk();
});

test("l'API répond en JSON sur son domaine, sous /v1", function () {
    $this->getJson(koryhubUrl('api', '/v1'))
        ->assertOk()
        ->assertJson(['version' => 'v1']);
});

test('la documentation répond sur son domaine', function () {
    $this->get(koryhubUrl('docs'))->assertOk();
});

test("l'administration demande une connexion", function () {
    $this->get(koryhubUrl('admin'))->assertRedirect(route('login'));
});

test("l'administration répond à un compte connecté", function () {
    $this->actingAs(User::factory()->create())
        ->get(koryhubUrl('admin'))
        ->assertOk();
});

test('un domaine inconnu répond 404', function () {
    $this->get('http://inconnu.test/')->assertNotFound();
});

test("les pages du site principal n'existent pas sur le domaine de l'API", function () {
    $this->get(koryhubUrl('api', '/dashboard'))
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

test('la connexion web reste sur le domaine principal', function () {
    expect(parse_url(route('login'), PHP_URL_HOST))->toBe(config('koryhub.domains.main'));

    $this->get(koryhubUrl('docs', '/login'))->assertNotFound();
});

test('la route de supervision répond sur chaque domaine', function (string $space) {
    $this->get(koryhubUrl($space, '/up'))->assertOk();
})->with(['main', 'api', 'docs', 'admin']);

test("derrière Cloudflare, l'adresse IP retenue est celle du visiteur", function () {
    Route::domain(config('koryhub.domains.main'))->get('/__ip', fn (Request $request) => $request->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.10'])
        ->withHeader('X-Forwarded-For', '41.202.207.5')
        ->get(koryhubUrl('main', '/__ip'))
        ->assertSeeText('41.202.207.5');
});

test('un en-tête X-Forwarded-For qui ne vient pas de Cloudflare est ignoré', function () {
    Route::domain(config('koryhub.domains.main'))->get('/__ip', fn (Request $request) => $request->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->withHeader('X-Forwarded-For', '41.202.207.5')
        ->get(koryhubUrl('main', '/__ip'))
        ->assertSeeText('203.0.113.7');
});
