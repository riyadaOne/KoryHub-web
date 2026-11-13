<?php

use App\Enums\SecurityEventType;
use App\Enums\TokenOrigin;
use App\Enums\TokenStatus;
use App\Exceptions\InvalidTransition;
use App\Models\AuditEvent;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\SecurityNotification;
use App\Services\Accounts\AccountService;
use App\Services\Cli\CliTokenService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * Jetons du CLI (W02) : création, statut, révocation, écrans S-41 et S-42.
 */

test('un jeton est préfixé kory_ et seule son empreinte est enregistrée', function () {
    $user = User::factory()->create();

    [$plain, $token] = app(CliTokenService::class)->create($user, 'CI GitHub', 90, TokenOrigin::Site);

    expect($plain)->toStartWith('kory_')
        ->and(strlen($plain))->toBe(5 + 40 + 8)
        ->and($token->token)->toBe(hash('sha256', $plain))
        ->and($token->token)->not->toContain('kory_')
        ->and($token->origin)->toBe(TokenOrigin::Site)
        ->and($token->expires_at->isSameDay(now()->addDays(90)))->toBeTrue()
        ->and(PersonalAccessToken::findToken($plain)?->is($token))->toBeTrue();
});

test('une durée hors de la liste est refusée, sans expiration est permis', function () {
    $user = User::factory()->create();
    $service = app(CliTokenService::class);

    [, $forever] = $service->create($user, 'Serveur', null, TokenOrigin::Site);
    expect($forever->expires_at)->toBeNull()->and($forever->status())->toBe(TokenStatus::Active);

    $service->create($user, 'Essai', 12, TokenOrigin::Site);
})->throws(InvalidArgumentException::class);

test('le statut est calculé à partir des dates', function () {
    $user = User::factory()->create();
    [, $token] = app(CliTokenService::class)->create($user, 'Essai', 30, TokenOrigin::Site);

    expect($token->status())->toBe(TokenStatus::Active);

    $this->travel(25)->days();
    expect($token->status())->toBe(TokenStatus::ExpiringSoon);

    $this->travel(6)->days();
    expect($token->status())->toBe(TokenStatus::Expired);
});

test('la création est notée dans le journal de sécurité et l\'audit, avec une notification', function () {
    Notification::fake();
    $user = User::factory()->create();

    [, $token] = app(CliTokenService::class)->create($user, 'CI GitHub', 30, TokenOrigin::Site);

    $event = $user->securityEvents()->sole();
    expect($event->type)->toBe(SecurityEventType::TokenCreated)
        ->and($event->token_id)->toBe($token->id)
        ->and(AuditEvent::where('action', 'token.created')->where('subject_id', $token->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, SecurityNotification::class, fn ($n) => $n->kind === 'token_created' && $n->params['token'] === 'CI GitHub');
});

test('un jeton révoqué garde sa ligne et ne peut pas être révoqué deux fois', function () {
    Notification::fake();
    $user = User::factory()->create();
    $service = app(CliTokenService::class);
    [, $token] = $service->create($user, 'Essai', 30, TokenOrigin::Site);

    $service->revoke($token, $user);

    expect($token->fresh()->status())->toBe(TokenStatus::Revoked)
        ->and($token->fresh()->revoked_by)->toBe($user->id)
        ->and($user->securityEvents()->where('type', SecurityEventType::TokenRevoked)->exists())->toBeTrue();
    Notification::assertSentTo($user, SecurityNotification::class, fn ($n) => $n->kind === 'token_revoked');

    $service->revoke($token->fresh(), $user);
})->throws(InvalidTransition::class);

test('un jeton expiré ne peut pas être révoqué', function () {
    $user = User::factory()->create();
    [, $token] = app(CliTokenService::class)->create($user, 'Essai', 7, TokenOrigin::Site);
    $this->travel(8)->days();

    app(CliTokenService::class)->revoke($token);
})->throws(InvalidTransition::class);

test('S-41 liste seulement les jetons du compte, avec leur statut', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $service = app(CliTokenService::class);
    $service->create($user, 'Mon jeton', 90, TokenOrigin::Site);
    $service->create($other, 'Jeton des autres', 90, TokenOrigin::Site);

    $this->actingAs($user)->get(route('tokens.index'))
        ->assertOk()
        ->assertSee('Mon jeton')
        ->assertDontSee('Jeton des autres')
        ->assertSee(__('tokens.status.active'));
});

test('S-41 révoque après l\'étape de confirmation, sans toucher aux jetons des autres', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $service = app(CliTokenService::class);
    [, $mine] = $service->create($user, 'Mon jeton', 90, TokenOrigin::Site);
    [, $theirs] = $service->create($other, 'Autre', 90, TokenOrigin::Site);
    $this->actingAs($user);

    Livewire::test('pages::settings.tokens')
        ->call('confirmRevoke', $mine->id)
        ->call('revoke')
        ->assertSet('notice', __('tokens.revoked'));

    expect($mine->fresh()->status())->toBe(TokenStatus::Revoked);

    Livewire::test('pages::settings.tokens')->call('confirmRevoke', $theirs->id)->assertNotFound();
    expect($theirs->fresh()->status())->toBe(TokenStatus::Active);
});

test('S-42 redemande le mot de passe avant d\'entrer', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tokens.create'))
        ->assertRedirect(route('password.confirm'));
});

test('S-42 crée le jeton et l\'affiche une seule fois', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('pages::settings.token-create')
        ->set('name', 'CI GitHub')
        ->set('lifetime', 'none')
        ->assertSee(__('tokens.no_expiry_title'))
        ->call('create')
        ->assertHasNoErrors();

    $plain = $component->get('plainToken');
    $component->assertSee($plain);

    $token = $user->tokens()->sole();
    expect($token->name)->toBe('CI GitHub')
        ->and($token->expires_at)->toBeNull()
        ->and($token->token)->toBe(hash('sha256', $plain));

    // La page suivante ne montre plus le jeton en clair.
    $this->get(route('tokens.index'))->assertDontSee($plain)->assertSee('CI GitHub');
});

test('S-42 refuse un nom vide ou une durée inconnue', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.token-create')
        ->set('name', '')
        ->set('lifetime', '12')
        ->call('create')
        ->assertHasErrors(['name', 'lifetime']);
});

test('la suppression du compte révoque ses jetons sans les supprimer', function () {
    $user = User::factory()->create();
    [, $token] = app(CliTokenService::class)->create($user, 'Essai', 90, TokenOrigin::Site);

    app(AccountService::class)->delete($user);

    expect($token->fresh())->not->toBeNull()
        ->and($token->fresh()->status())->toBe(TokenStatus::Revoked);
});
