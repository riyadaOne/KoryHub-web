<?php

use App\Enums\NotificationType;
use App\Enums\TokenOrigin;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Cli\CliTokenService;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Espace du développeur (W08.2, W08.4, W08.6, F-ADM-07, F-ADM-12) : S-30 complet, S-31 toutes les
 * notifications, S-47 Mon activité limitée au compte.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->user = User::factory()->create(['handle' => 'moussa']);
});

test('S-30 : premiers pas sans module ; ensuite jeton qui expire, notifications, modules, certifications', function () {
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee('kory login')->assertDontSee('data-test="token-expiring"', false);

    publishKory($this->user, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/A.php' => "<?php\n"]));
    app(CliTokenService::class)->create($this->user, 'Serveur CI', 30, TokenOrigin::CliLogin);
    app(CliTokenService::class)->create($this->user, 'Portable', 90, TokenOrigin::CliLogin);
    $this->travel(25)->days();

    $this->get(route('dashboard'))->assertOk()
        ->assertSee('data-test="token-expiring"', false)->assertSee('Serveur CI')->assertDontSee('« Portable »')
        ->assertSee('data-test="my-modules"', false)->assertSee('paiement')
        ->assertSee(__('notifications.version_published.title', ['module' => '@moussa/paiement', 'version' => '1.0.0']))
        ->assertDontSee('kory init');
});

test('S-31 : toutes les notifications, filtre non lues, tout marquer comme lu, ouvrir', function () {
    foreach (['1.0.0', '1.1.0'] as $version) {
        $this->user->notify(new ModuleNotification(NotificationType::NewVersionWatched, ['module' => '@awa-diop/orange-money-ci', 'version' => $version]));
    }
    $this->user->notifications()->latest()->first()->markAsRead();

    $this->actingAs($this->user)->get(route('notifications.index'))->assertOk()
        ->assertSee('orange-money-ci 1.0.0')->assertSee('orange-money-ci 1.1.0');
    $this->get(route('dashboard'))->assertSee('data-test="see-all-notifications"', false);

    Livewire::actingAs($this->user)->test('pages::notifications')->set('filter', 'unread')
        ->assertSee(__('notifications.page.unread', ['count' => 1]))
        ->call('markAllRead');
    expect($this->user->unreadNotifications()->count())->toBe(0);

    $id = $this->user->notifications()->get()->first(fn ($n) => $n->data['params']['version'] === '1.1.0')->id;
    $this->user->notifications()->update(['read_at' => null]);
    Livewire::actingAs($this->user)->test('pages::notifications')->call('open', $id)
        ->assertRedirect(route('modules.version', ['author' => 'awa-diop', 'module' => 'orange-money-ci', 'number' => '1.1.0']));
    expect($this->user->notifications()->find($id)->read_at)->not->toBeNull();
});

test('S-47 : seulement les actions du compte, libellé traduit, champ personnel masqué', function () {
    publishKory($this->user, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/A.php' => "<?php\n"]));
    app(AuditLogger::class)->record('account.updated', $this->user, ['name' => ['Moussa', 'Moussa Mahamat']], actor: $this->user);
    $other = User::factory()->create(['handle' => 'awa-diop']);
    app(AuditLogger::class)->record('token.created', $other, ['name' => [null, 'Secret de awa']], actor: $other);

    $this->actingAs($this->user)->get(route('activity.index'))->assertOk()
        ->assertSee(__('audit.actions.version_published'))->assertSee('@moussa/paiement 1.0.0')
        ->assertSee(__('audit.redacted'))->assertDontSee('Moussa Mahamat')
        ->assertDontSee('@awa-diop');
    $this->actingAs($other)->get(route('activity.index'))->assertOk()->assertDontSee('@moussa/paiement');
});
