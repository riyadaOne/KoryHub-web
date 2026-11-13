<?php

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Services\Accounts\SecurityLog;
use Livewire\Livewire;

/*
 * Journal de sécurité (F-AUT-19, S-40) : rempli par les connexions et les changements de
 * sécurité, visible par l'intéressé seul.
 */

test('une connexion réussie est notée avec l\'adresse IP et le navigateur', function () {
    $user = User::factory()->create(['email' => 'moussa@exemple.td']);

    $this->withServerVariables(['REMOTE_ADDR' => '41.202.207.5'])
        ->withHeader('User-Agent', 'Mozilla/5.0 Chrome')
        ->post(route('login.store'), ['email' => 'moussa@exemple.td', 'password' => 'password']);

    $event = $user->securityEvents()->sole();
    expect($event->type)->toBe(SecurityEventType::Login)
        ->and($event->ip)->toBe('41.202.207.5')
        ->and($event->user_agent)->toBe('Mozilla/5.0 Chrome');
});

test('un mauvais mot de passe est noté sur le compte visé', function () {
    $user = User::factory()->create(['email' => 'moussa@exemple.td']);

    $this->post(route('login.store'), ['email' => 'moussa@exemple.td', 'password' => 'mauvais']);

    expect($user->securityEvents()->sole()->type)->toBe(SecurityEventType::LoginFailed);
});

test('une tentative sur un e-mail inconnu ne note rien', function () {
    $this->post(route('login.store'), ['email' => 'inconnu@exemple.td', 'password' => 'mauvais']);

    $this->assertDatabaseCount('security_events', 0);
});

test('le changement de mot de passe et d\'e-mail est noté', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'nouveau-mot-de-passe')
        ->set('password_confirmation', 'nouveau-mot-de-passe')
        ->call('updatePassword')
        ->assertHasNoErrors();

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', 'nouvelle@exemple.td')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->securityEvents()->pluck('type')->all())
        ->toEqualCanonicalizing([SecurityEventType::PasswordChanged, SecurityEventType::EmailChanged]);
});

test('le journal ne montre que les événements du compte connecté', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '41.202.207.5']);
    app(SecurityLog::class)->record($owner, SecurityEventType::Login);
    $this->withServerVariables(['REMOTE_ADDR' => '102.89.44.18']);
    request()->server->set('REMOTE_ADDR', '102.89.44.18');
    app(SecurityLog::class)->record($other, SecurityEventType::Login);

    $this->actingAs($owner)
        ->get(route('security-log.index'))
        ->assertOk()
        ->assertSee('data-test="security-log-table"', false)
        ->assertDontSee('102.89.44.18');
});

test('le journal demande une connexion', function () {
    $this->get(route('security-log.index'))->assertRedirect(route('login'));
});
