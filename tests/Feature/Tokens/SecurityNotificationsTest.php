<?php

use App\Enums\SecurityEventType;
use App\Enums\TokenOrigin;
use App\Models\User;
use App\Notifications\SecurityNotification;
use App\Services\Accounts\SecurityLog;
use App\Services\Cli\CliTokenService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * Notifications de sécurité (W02, TRD §19) : e-mail et cloche, relance J-7.
 */

test('l\'e-mail part dans la langue du compte, avec le jeton et l\'adresse IP', function () {
    $user = User::factory()->create(['locale' => 'fr']);
    $notification = new SecurityNotification('cli_login', ['token' => 'kory · Windows', 'ip' => '41.202.207.5', 'expires_at' => null]);

    app()->setLocale('fr');
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('Nouvelle connexion du CLI')
        ->and(implode(' ', $mail->introLines))->toContain('kory · Windows')->toContain('41.202.207.5')->toContain('jamais')
        ->and($mail->actionUrl)->toBe(route('tokens.index'))
        ->and($notification->via($user))->toBe(['mail', 'database']);
});

test('changer de mot de passe envoie une notification', function () {
    Notification::fake();
    $user = User::factory()->create();

    app(SecurityLog::class)->record($user, SecurityEventType::PasswordChanged);

    Notification::assertSentTo($user, SecurityNotification::class, fn ($n) => $n->kind === 'password_changed');
});

test('la cloche montre le nombre de non lues et les marque comme lues', function () {
    $user = User::factory()->create();
    app(CliTokenService::class)->create($user, 'CI GitHub', 90, TokenOrigin::Site);
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertSee('data-test="notification-count"', false);

    Livewire::test('notification-bell')
        ->assertSee(__('notifications.token_created.title', ['token' => 'CI GitHub']))
        ->call('markAllRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('la relance J-7 part une seule fois par jeton', function () {
    Notification::fake();
    $user = User::factory()->create();
    $service = app(CliTokenService::class);
    $service->create($user, 'Bientôt', 7, TokenOrigin::Site);
    $service->create($user, 'Plus tard', 90, TokenOrigin::Site);
    [, $revoked] = $service->create($user, 'Révoqué', 7, TokenOrigin::Site);
    $service->revoke($revoked);

    $this->artisan('koryhub:tokens-remind')->assertSuccessful();
    $this->artisan('koryhub:tokens-remind')->assertSuccessful();

    // 3 créations, 1 révocation, 1 seule relance.
    Notification::assertSentToTimes($user, SecurityNotification::class, 5);
    Notification::assertSentTo($user, SecurityNotification::class, fn ($n) => $n->kind === 'token_expiring' && $n->params['token'] === 'Bientôt');
    Notification::assertNotSentTo($user, SecurityNotification::class, fn ($n) => $n->kind === 'token_expiring' && $n->params['token'] !== 'Bientôt');
});
