<?php

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use App\Notifications\NotificationText;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/*
 * Choix de la langue et sens d'écriture (TRD §20, UXUI §9, NF-06).
 */

function mainUrl(string $path = '/'): string
{
    return 'http://'.config('koryhub.domains.main').$path;
}

test("sans indication, la langue est l'anglais, de gauche à droite", function () {
    $this->get(mainUrl())
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('dir="ltr"', false);
});

test('la langue du navigateur est retenue si elle est proposée', function () {
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.8')
        ->get(mainUrl())
        ->assertSee('lang="fr"', false);
});

test("une langue du navigateur non proposée donne l'anglais", function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
        ->get(mainUrl())
        ->assertSee('lang="en"', false);
});

test("le cookie l'emporte sur le navigateur ; l'arabe s'écrit de droite à gauche", function () {
    $this->withCookie(SetLocale::COOKIE, 'ar')
        ->withHeader('Accept-Language', 'fr-FR')
        ->get(mainUrl())
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false);
});

test('choisir une langue pose le cookie et revient à la page précédente', function () {
    $this->from(mainUrl('/'))
        ->post(mainUrl('/locale/pt'))
        ->assertRedirect(mainUrl('/'))
        ->assertCookie(SetLocale::COOKIE, 'pt');
});

test('une langue inconnue est refusée', function () {
    $this->post(mainUrl('/locale/de'))->assertNotFound();
});

test("l'API suit l'en-tête Accept-Language", function () {
    Route::domain(config('koryhub.domains.api'))->middleware('api')
        ->get('/__locale', fn () => app()->getLocale());

    $this->withHeader('Accept-Language', 'pt-BR')
        ->get('http://'.config('koryhub.domains.api').'/__locale')
        ->assertSeeText('pt');
});

test('chaque langue a un sens d\'écriture et un nom', function (Locale $locale) {
    expect($locale->direction())->toBeIn(['ltr', 'rtl'])
        ->and($locale->nativeName())->not->toBeEmpty();
})->with(Locale::cases());

test('une date est écrite en UTC pour être affichée dans le fuseau du navigateur', function () {
    $html = Blade::render('<x-relative-date :at="$at" />', ['at' => '2026-11-13 09:30:00']);

    expect($html)->toContain('datetime="2026-11-13T09:30:00Z"')
        ->and($html)->toContain('data-relative-date');
});

test('en arabe, le nom du module est isolé dans les notifications (le « @ » reste en tête)', function () {
    app()->setLocale('ar');
    expect(NotificationText::replacements(['module' => '@fatime-zakaria/facture'])['module'])
        ->toBe("\u{2066}@fatime-zakaria/facture\u{2069}");

    app()->setLocale('fr');
    expect(NotificationText::replacements(['module' => '@fatime-zakaria/facture'])['module'])
        ->toBe('@fatime-zakaria/facture');
});
