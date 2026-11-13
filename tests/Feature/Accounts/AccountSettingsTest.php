<?php

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Profil, langue, compte suspendu, tableau de bord et commande admin (W01).
 */

test('le pays du profil est enregistré s\'il existe, refusé sinon', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')->set('country', 'TD')->call('updateProfileInformation')->assertHasNoErrors();
    expect($user->fresh()->country)->toBe('TD');

    Livewire::test('pages::settings.profile')->set('country', 'XX')->call('updateProfileInformation')->assertHasErrors('country');
});

test('la photo de profil est enregistrée et remplace l\'ancienne', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')->set('photo', UploadedFile::fake()->image('a.png'))->assertHasNoErrors();
    $first = $user->fresh()->avatar_path;

    Livewire::test('pages::settings.profile')->set('photo', UploadedFile::fake()->image('b.webp'))->assertHasNoErrors();

    Storage::disk('public')->assertMissing($first)->assertExists($user->fresh()->avatar_path);
});

test('une photo trop lourde ou qui n\'est pas une image est refusée', function (UploadedFile $file) {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.profile')->set('photo', $file)->assertHasErrors('photo');
})->with([
    'trop lourde' => fn () => UploadedFile::fake()->image('grande.jpg')->size(3000),
    'pas une image' => fn () => UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml'),
]);

test('la langue du compte l\'emporte sur le cookie et le navigateur', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $this->actingAs($user)
        ->withCookie(SetLocale::COOKIE, 'fr')
        ->withHeader('Accept-Language', 'pt')
        ->get(route('dashboard'))
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false);
});

test('changer de langue dans les réglages l\'enregistre sur le compte', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $this->actingAs($user);

    Livewire::test('pages::settings.appearance')->set('locale', 'pt')->assertRedirect(route('appearance.edit'));

    expect($user->fresh()->locale)->toBe('pt');
});

test('le sélecteur du pied de page enregistre aussi la langue du compte connecté', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)->post(route('locale.update', 'fr'));

    expect($user->fresh()->locale)->toBe('fr');
});

test('un compte suspendu est déconnecté avec un message', function () {
    $this->actingAs(User::factory()->suspended()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('account.suspended_message'));

    $this->assertGuest();
});

test('le tableau de bord rappelle de vérifier l\'adresse e-mail', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="unverified-banner"', false);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertDontSee('data-test="unverified-banner"', false)
        ->assertSee('kory login');
});

test('les e-mails du compte partent dans la langue du compte', function () {
    $user = User::factory()->create(['locale' => 'fr']);

    expect($user->preferredLocale())->toBe('fr');

    app()->setLocale($user->preferredLocale());
    $reset = (new ResetPassword('jeton'))->toMail($user);
    $verify = (new VerifyEmail)->toMail($user);

    expect($reset->subject)->toBe('Réinitialisation du mot de passe')
        ->and($reset->introLines[0])->toContain('réinitialisation du mot de passe')
        ->and($verify->subject)->toBe("Vérifier l'adresse e-mail");
});

test('la commande koryhub:admin donne puis retire le rôle admin', function () {
    $user = User::factory()->create(['email' => 'achta@riyadaone.td']);

    $this->artisan('koryhub:admin', ['email' => 'achta@riyadaone.td'])->assertSuccessful();
    expect($user->fresh()->isAdmin())->toBeTrue();

    $this->artisan('koryhub:admin', ['email' => 'achta@riyadaone.td', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->isAdmin())->toBeFalse();

    $this->artisan('koryhub:admin', ['email' => 'inconnu@exemple.td'])->assertFailed();
});
