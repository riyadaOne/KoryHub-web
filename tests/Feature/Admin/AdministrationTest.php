<?php

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use App\Services\Admin\CategoryService;
use App\Services\Audit\AuditLogger;
use App\Services\Moderation\ModerationRefused;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
 * Administration (W08.2 à W08.4, W08.6, F-ADM-03, F-ADM-06, F-ADM-11, F-RCH-11) : chaque écran
 * protégé, catégories, journal d'audit en lecture seule, canaux d'alerte de l'admin.
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['handle' => 'achta', 'role' => Role::Admin]);
    $this->admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    $this->developer = User::factory()->create(['handle' => 'moussa']);
    $this->adminUrl = 'http://'.config('koryhub.domains.admin');
    $this->categories = app(CategoryService::class);
});

test('chaque route de l\'administration refuse un développeur (F-ADM-03)', function () {
    $routes = collect(Route::getRoutes()->getRoutesByName())->filter(fn ($route, $name) => str_starts_with($name, 'admin.') && in_array('GET', $route->methods(), true));
    expect($routes->count())->toBeGreaterThan(12);

    foreach ($routes as $route) {
        $uri = preg_replace('/\{[^}]+\}/', '01jd0000000000000000000000', $route->uri());
        // Un identifiant inexistant peut répondre 404 avant le contrôle du rôle : jamais 200.
        $status = $this->actingAs($this->developer)->get($this->adminUrl.'/'.ltrim($uri, '/'))->status();
        expect($status)->toBeIn(str_contains($route->uri(), '{') ? [403, 404] : [403]);
    }
});

test('catégories : création dans les 4 langues, identifiant figé et unique, ordre, désactivation, suppression', function () {
    $names = ['fr' => 'Paiements', 'en' => 'Payments', 'ar' => 'المدفوعات', 'pt' => 'Pagamentos'];
    $payments = $this->categories->create('payments', $names, $this->admin);
    $maps = $this->categories->create('maps', ['fr' => 'Cartes', 'en' => 'Maps', 'ar' => 'خرائط', 'pt' => 'Mapas'], $this->admin);

    expect(fn () => $this->categories->create('payments', $names, $this->admin))->toThrow(ModerationRefused::class)
        ->and(fn () => $this->categories->create('Mauvais Slug', $names, $this->admin))->toThrow(ModerationRefused::class)
        ->and(fn () => $this->categories->create('docs', ['fr' => 'Docs'], $this->admin))->toThrow(ModerationRefused::class, __('admin.categories.errors.names'))
        ->and(fn () => $this->categories->create('docs', $names, $this->developer))->toThrow(ModerationRefused::class);

    $this->categories->move($maps, -1, $this->admin);
    expect(Category::orderBy('position')->pluck('slug')->all())->toBe(['maps', 'payments']);

    $this->categories->rename($payments, [...$names, 'fr' => 'Paiement mobile'], $this->admin);
    expect(AuditEvent::where('action', 'category.updated')->sole()->changes()->pluck('field')->all())->toBe(['name_fr']);

    Module::query()->create(['author_id' => $this->developer->id, 'name' => 'paiement', 'visibility' => 'private', 'status' => 'active', 'category_id' => $payments->id]);
    expect(fn () => $this->categories->delete($payments, $this->admin))->toThrow(ModerationRefused::class, __('admin.categories.errors.used'));

    $this->categories->setActive($payments, false, $this->admin);
    $this->get('/c/payments')->assertNotFound();

    $this->categories->delete($maps, $this->admin);
    expect(Category::whereKey($maps->id)->exists())->toBeFalse()->and(Category::withTrashed()->whereKey($maps->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)->get($this->adminUrl.'/categories')->assertOk()->assertSee('Payments')->assertSee(__('admin.categories.inactive'));
    Livewire::actingAs($this->admin)->test('pages::admin.categories')
        ->call('create')->set('slug', 'offline-sync')->set('names', ['fr' => 'Hors ligne', 'en' => 'Offline', 'ar' => 'دون اتصال', 'pt' => 'Offline'])
        ->call('save')->assertHasNoErrors();
    expect(Category::where('slug', 'offline-sync')->exists())->toBeTrue();
});

test('journal d\'audit : filtres, détail avant et après, aucun bouton d\'écriture', function () {
    $logger = app(AuditLogger::class);
    $event = $logger->record('module.updated', $this->developer, ['description' => ['Ancienne', 'Nouvelle'], 'email' => ['a@b.c', 'd@e.f']], 'Correction.', $this->developer);
    $logger->record('category.created', $this->admin, [], 'Création par Achta.', $this->admin);

    $this->actingAs($this->admin)->get($this->adminUrl.'/audit?actor=@moussa')->assertOk()
        ->assertSee('Correction.')->assertDontSee('Création par Achta.');
    $this->get($this->adminUrl.'/audit?action=category.created')->assertOk()->assertSee('@achta');
    $this->get($this->adminUrl.'/audit/'.$event->id)->assertOk()
        ->assertSee('Ancienne')->assertSee('Nouvelle')->assertSee('Correction.')
        ->assertSee(__('audit.redacted'))->assertDontSee('a@b.c')
        ->assertDontSee('data-test="confirm-', false);
});

test('S-73 : canaux des alertes admin ; l\'incident part toujours par e-mail ; plus de groupe admin sur S-43', function () {
    Livewire::actingAs($this->admin)->test('pages::admin.settings')
        ->set('preferences.admin_report_stale.mail', false)
        ->set('preferences.admin_incident.mail', false)
        ->set('preferences.admin_weekly_report.database', true)
        ->call('save')->assertSet('saved', true);

    $admin = $this->admin->fresh();
    expect($admin->wantsNotification(NotificationType::AdminReportStale, 'mail'))->toBeFalse()
        ->and($admin->wantsNotification(NotificationType::AdminIncident, 'mail'))->toBeTrue()
        ->and($admin->wantsNotification(NotificationType::AdminWeeklyReport, 'database'))->toBeTrue();

    $this->actingAs($this->admin)->get(route('notifications.edit'))->assertOk()->assertDontSee(__('notifications.preferences.group_admin'));
});
