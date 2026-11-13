<?php

use App\Enums\AccountStatus;
use App\Enums\SecurityEventType;
use App\Exceptions\InvalidTransition;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Accounts\AccountService;
use App\Services\Accounts\SecurityLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Suppression du compte (F-AUT-17, RGPD, APPFLOW §5.9) : données personnelles effacées,
 * ligne gardée pour réserver le nom @auteur, journal de sécurité effacé, trace d'audit.
 */

test('la suppression efface les données personnelles et garde le nom réservé', function () {
    Storage::fake('public');
    $user = User::factory()->create(['handle' => 'moussa', 'country' => 'TD']);
    $user->update(['avatar_path' => UploadedFile::fake()->image('photo.jpg')->store('avatars', 'public')]);
    app(SecurityLog::class)->record($user, SecurityEventType::Login);

    app(AccountService::class)->delete($user);

    $user->refresh();
    expect($user->status)->toBe(AccountStatus::Deleted)
        ->and($user->handle)->toBe('moussa')
        ->and($user->only(['name', 'email', 'password', 'country', 'avatar_path']))->each->toBeNull()
        ->and($user->erased_at)->not->toBeNull()
        ->and($user->securityEvents()->count())->toBe(0);

    Storage::disk('public')->assertDirectoryEmpty('avatars');
});

test('la suppression laisse une trace d\'audit sans donnée personnelle', function () {
    $user = User::factory()->create(['email' => 'moussa@exemple.td']);

    app(AccountService::class)->delete($user);

    $event = AuditEvent::firstWhere('action', 'account.deleted');
    expect($event->subject_id)->toBe($user->id)
        ->and($event->changes->pluck('after')->all())->toBe(['deleted'])
        ->and($event->changes->pluck('before')->implode(' ').$event->reason)->not->toContain('moussa@exemple.td');
});

test('un compte supprimé ne peut plus se connecter', function () {
    $user = User::factory()->create(['email' => 'moussa@exemple.td']);
    app(AccountService::class)->delete($user);

    $this->post(route('login.store'), ['email' => 'moussa@exemple.td', 'password' => 'password']);

    $this->assertGuest();
});

test('un compte ne peut pas être supprimé deux fois', function () {
    $user = User::factory()->create();
    app(AccountService::class)->delete($user);

    app(AccountService::class)->delete($user->fresh());
})->throws(InvalidTransition::class);

test('la confirmation exige le nom @auteur exact', function () {
    $user = User::factory()->create(['handle' => 'moussa']);
    $this->actingAs($user);

    Livewire::test('pages::settings.delete-user-modal')
        ->set('handle', 'mousa')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['handle']);

    expect($user->fresh()->status)->toBe(AccountStatus::Active);
});
