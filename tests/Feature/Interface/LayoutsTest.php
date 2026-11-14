<?php

use App\Enums\Locale;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

/*
 * Mises en page et composants de base (UXUI §5, §6.2, maquette de référence).
 */

function spaceUrl(string $space, string $path = '/'): string
{
    return 'http://'.config("koryhub.domains.{$space}").$path;
}

test("l'accueil montre le logo, la connexion et le choix des 4 langues", function () {
    $response = $this->get(spaceUrl('main'))->assertOk();

    $response->assertSee('Kory')->assertSee(route('login'), false);

    foreach (Locale::cases() as $locale) {
        $response->assertSee($locale->nativeName())->assertSee('data-test="locale-'.$locale->value.'"', false);
    }
});

test('la langue en cours est marquée dans le sélecteur', function () {
    $this->withHeader('Accept-Language', 'pt')
        ->get(spaceUrl('main'))
        ->assertSee(__('common.home_title', locale: 'pt'))
        ->assertSeeInOrder(['aria-current="true"', 'data-test="locale-pt"'], false);
});

test("l'espace connecté reprend l'en-tête public avec le menu du compte", function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="logout-button"', false)
        ->assertSee(route('docs.home'), false);
});

test("l'administration affiche la mention Administration", function () {
    // Depuis W06, l'administration est réservée aux admins avec double authentification.
    $admin = User::factory()->create(['role' => Role::Admin]);
    $admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($admin)
        ->get(spaceUrl('admin'))
        ->assertOk()
        ->assertSee('data-test="admin-badge"', false)
        ->assertSee(__('admin.title'));
});

test('la documentation utilise la mise en page publique', function () {
    $this->get(spaceUrl('docs'))
        ->assertOk()
        ->assertSee(__('docs.title'))
        ->assertSee('data-test="locale-ar"', false);
});

test('la commande copiable affiche la commande et le bouton Copier', function () {
    $html = Blade::render('<x-copy-command command="kory pull @moussa/mobile-money-resilient" />');

    expect($html)->toContain('kory pull @moussa/mobile-money-resilient')
        ->toContain('data-test="copy-button"')
        ->toContain(__('common.copy'));
});

test('la confirmation demande de saisir la valeur exacte quand elle est exigée', function () {
    $html = Blade::render(<<<'BLADE'
        <x-confirm-step name="supprimer" heading="Supprimer le module" confirm-value="@moussa/essai">
            <x-slot:action><button>OK</button></x-slot:action>
        </x-confirm-step>
        BLADE);

    expect($html)->toContain('data-test="confirm-input"')->toContain('@moussa/essai');
});

test("la bannière d'avertissement affiche sa cause", function () {
    $html = Blade::render('<x-warning-banner heading="Version dépréciée">Passez en 1.1.0.</x-warning-banner>');

    expect($html)->toContain('Version dépréciée')->toContain('Passez en 1.1.0.');
});
