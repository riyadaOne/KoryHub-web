<?php

use App\Models\Category;
use App\Models\User;
use App\Services\Accounts\AccountService;
use App\Services\Modules\ModuleLifecycle;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Découverte (W05 bloc C, F-RCH-11, F-RCH-12, F-RCH-16) : accueil, catégorie, profil public,
 * balises de partage. Jamais de module privé ni de donnée privée.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa', 'name' => 'Moussa Mahamat', 'country' => 'TD', 'email' => 'moussa@demo.test']);
    $this->payments = Category::create(['slug' => 'payments', 'position' => 1, 'is_active' => true]);
    $this->payments->translations()->create(['locale' => 'en', 'name' => 'Payments', 'description' => 'Mobile Money and cards.']);

    $this->public = publishKory($this->author, TarBuilder::kory('@moussa/paiement', manifest: ['description' => 'Encaissement public.']))->module;
    app(ModuleLifecycle::class)->updateSettings($this->public, ['description' => 'Encaissement public.', 'category_id' => $this->payments->id, 'tags' => [], 'visibility' => $this->public->visibility], $this->author);
    $this->private = publishKory($this->author, TarBuilder::kory('@moussa/brouillon', manifest: ['language' => 'dart']), 'brouillon', ['visibility' => 'private'])->module;
});

test('S-01 : « Mis en avant » seulement si l\'admin a coché un module ; récents et catégories ; commande d\'installation', function () {
    $this->get('/')->assertOk()
        ->assertDontSee('data-test="featured"', false)
        ->assertSee('data-test="recent"', false)
        ->assertSee('@moussa/')
        ->assertDontSee('brouillon')
        ->assertSee('irm https://koryhub.dev/install.ps1 | iex')
        ->assertSee('Payments');

    $this->public->forceFill(['is_featured' => true, 'featured_at' => now()])->save();
    $this->get('/')->assertSee('data-test="featured"', false);

    $this->public->forceFill(['reviews_hidden' => true])->save();
    $this->get('/')->assertDontSee('data-test="featured"', false);
});

test('S-09 : modules publics de la catégorie, filtre de langage ; catégorie inconnue : 404', function () {
    $this->get(route('categories.show', 'payments'))->assertOk()
        ->assertSee('Mobile Money and cards.')
        ->assertSee('@moussa/')->assertSee('paiement')->assertDontSee('brouillon');
    $this->get(route('categories.show', ['slug' => 'payments', 'language' => 'dart']))->assertOk()->assertSee('data-test="category-empty"', false);
    $this->get(route('categories.show', 'inconnue'))->assertNotFound();
});

test('S-08 : profil public sans e-mail ni module privé ; balises de partage ; compte supprimé', function () {
    $this->get(route('profiles.show', 'moussa'))->assertOk()
        ->assertSee('Moussa Mahamat')
        ->assertSee('paiement')
        ->assertDontSee('brouillon')
        ->assertDontSee('moussa@demo.test')
        ->assertSee('<meta property="og:title" content="@moussa - KoryHub" />', false);

    $this->get(route('modules.show', ['author' => 'moussa', 'module' => 'paiement']))
        ->assertSee('<meta name="description" content="Encaissement public." />', false);

    $this->get(route('profiles.show', 'inconnu'))->assertNotFound();

    $someone = User::factory()->create(['handle' => 'parti']);
    app(AccountService::class)->delete($someone, $someone);
    $this->get(route('profiles.show', 'parti'))->assertOk()->assertSee(__('discover.profile_deleted', ['handle' => '@parti']));
});
