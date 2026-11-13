<?php

use App\Models\User;
use App\Services\Accounts\AccountService;

/*
 * Inscription avec le nom @auteur (W01, F-AUT-01) et règle du mot de passe (TRD §7.1).
 */

function registration(array $overrides = []): array
{
    return [
        'name' => 'Moussa Mahamat',
        'handle' => 'moussa',
        'email' => 'moussa@exemple.td',
        'password' => 'mot-de-passe-solide',
        'password_confirmation' => 'mot-de-passe-solide',
        ...$overrides,
    ];
}

test("le nom @auteur est enregistré en minuscules, avec la langue de l'inscription", function () {
    $this->withHeader('Accept-Language', 'fr')
        ->post(route('register.store'), registration(['handle' => 'Moussa-TD']))
        ->assertSessionHasNoErrors();

    $user = User::firstWhere('email', 'moussa@exemple.td');

    expect($user->handle)->toBe('moussa-td')->and($user->locale)->toBe('fr');
});

test('un nom @auteur mal formé est refusé', function (string $handle) {
    $this->post(route('register.store'), registration(['handle' => $handle]))
        ->assertSessionHasErrors('handle');
})->with(['m', '-moussa', 'moussa-', 'mou ssa', 'moussa_td', 'moussa.td', str_repeat('a', 40)]);

test('un nom réservé est refusé', function (string $handle) {
    $this->post(route('register.store'), registration(['handle' => $handle]))
        ->assertSessionHasErrors('handle');
})->with(['admin', 'api', 'koryhub', 'kory', 'settings']);

test('un nom déjà pris est refusé, même par un compte supprimé', function () {
    $owner = User::factory()->create(['handle' => 'achta']);
    app(AccountService::class)->delete($owner);

    $this->post(route('register.store'), registration(['handle' => 'achta']))
        ->assertSessionHasErrors('handle');
});

test('un mot de passe de moins de 12 caractères est refusé', function () {
    $this->post(route('register.store'), registration(['password' => 'court123', 'password_confirmation' => 'court123']))
        ->assertSessionHasErrors('password');
});

test("l'écran d'inscription contient le champ du nom @auteur", function () {
    $this->get(route('register'))->assertOk()->assertSee('data-test="handle-input"', false);
});
