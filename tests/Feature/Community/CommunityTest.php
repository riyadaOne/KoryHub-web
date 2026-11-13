<?php

use App\Enums\NotificationType;
use App\Enums\WatchLevel;
use App\Models\AuditEvent;
use App\Models\ModuleVersion;
use App\Models\Review;
use App\Models\Star;
use App\Models\User;
use App\Models\Watch;
use App\Notifications\ModuleNotification;
use App\Services\Accounts\AccountService;
use App\Services\Community\ReviewService;
use App\Services\Modules\DownloadService;
use App\Services\Modules\ModuleLifecycle;
use App\Services\Modules\ModuleRuleViolation;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Communauté (W05 bloc A, F-RCH-07 à F-RCH-09, F-RCH-17, F-REG-19) : avis et notes vérifiées,
 * favoris, abonnements à 3 niveaux, audience des événements d'un module.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    $this->hassan = User::factory()->create(['handle' => 'hassan']);
    $this->awa = User::factory()->create(['handle' => 'awa']);
    $this->version = publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    $this->module = $this->version->module;
    $this->reviews = app(ReviewService::class);
});

function installedBy(ModuleVersion $version, User $user): void
{
    RateLimiter::clear('downloads:user:'.$user->id);
    app(DownloadService::class)->download($version, $user, '127.0.0.1');
}

test('un avis par développeur : modifié, supprimé puis restauré ; jamais sur son module ; historisé sans le texte', function () {
    expect(fn () => $this->reviews->save($this->module, $this->author, 5, 'Le mien'))->toThrow(ModuleRuleViolation::class);

    $review = $this->reviews->save($this->module, $this->hassan, 4, 'Très bon');
    $this->reviews->save($this->module, $this->hassan, 5, 'Excellent');
    expect(Review::count())->toBe(1)->and($review->fresh()->rating)->toBe(5);

    $this->reviews->delete($review->fresh(), $this->hassan);
    expect(Review::count())->toBe(0);

    $this->reviews->save($this->module, $this->hassan, 3, null);
    expect(Review::withTrashed()->count())->toBe(1)->and(Review::sole()->rating)->toBe(3);

    $actions = AuditEvent::query()->where('subject_id', $review->id)->orderBy('occurred_at')->pluck('action')->all();
    expect($actions)->toBe(['review.created', 'review.updated', 'review.deleted', 'review.restored']);
    $comment = AuditEvent::where('action', 'review.created')->sole()->changes()->where('field', 'comment')->sole();
    expect((string) $comment->after)->not->toContain('Très bon');
});

test('seules les notes vérifiées comptent ; une note devient vérifiée au premier téléchargement connecté', function () {
    $this->reviews->save($this->module, $this->hassan, 2, null);
    expect($this->module->fresh()->rating_count)->toBe(0)->and($this->module->fresh()->rating_avg)->toBeNull();

    installedBy($this->version, $this->hassan);
    expect($this->module->fresh()->rating_count)->toBe(1)->and($this->module->fresh()->rating_avg)->toBe(2.0);

    installedBy($this->version, $this->awa);
    $this->reviews->save($this->module, $this->awa, 5, null);
    expect($this->module->fresh()->rating_avg)->toBe(3.5);

    app(AccountService::class)->delete($this->awa, $this->awa);
    expect($this->module->fresh()->rating_count)->toBe(1);
});

test('S-07 : badge « A utilisé ce module », formulaire, avis masqués par l\'auteur', function () {
    installedBy($this->version, $this->hassan);
    $this->reviews->save($this->module, $this->hassan, 5, 'Plus de double débit');
    $this->reviews->save($this->module, $this->awa, 3, 'Pas encore utilisé');

    $this->get(route('modules.reviews', ['author' => 'moussa', 'module' => 'paiement']))
        ->assertOk()
        ->assertSee('Plus de double débit')
        ->assertSee(__('community.reviews.verified'))
        ->assertSee(__('community.reviews.login'))
        ->assertSee(trans_choice('community.reviews.verified_count', 1, ['count' => 1]));

    $this->actingAs($this->awa);
    Livewire::test('pages::modules.reviews', ['author' => 'moussa', 'module' => 'paiement'])
        ->call('edit')->assertSet('rating', 3)
        ->set('rating', 4)->set('comment', 'Utilisé finalement')
        ->call('save')->assertHasNoErrors()->assertSee(__('community.reviews.saved'));
    expect(Review::where('user_id', $this->awa->id)->sole()->rating)->toBe(4);

    $this->actingAs($this->author);
    Livewire::test('pages::modules.reviews', ['author' => 'moussa', 'module' => 'paiement'])->assertDontSee(__('community.reviews.write'));

    app(ModuleLifecycle::class)->updateSettings($this->module, [
        'description' => null, 'category_id' => null, 'tags' => [], 'visibility' => $this->module->visibility, 'reviews_hidden' => true,
    ], $this->author);
    expect(AuditEvent::where('action', 'module.updated')->latest('occurred_at')->first()->changes()->pluck('field')->all())->toContain('reviews_hidden');
    $this->get(route('modules.reviews', ['author' => 'moussa', 'module' => 'paiement']))
        ->assertSee(__('community.reviews.hidden'))->assertDontSee('Plus de double débit');
});

test('favori et abonnement depuis la fiche ; S-44 et S-45 ; effacés avec le compte', function () {
    $this->actingAs($this->hassan);
    Livewire::test('module-actions', ['moduleId' => $this->module->id])
        ->call('toggleStar')->assertSee('· 1')
        ->call('setWatch', 'releases')->assertSee(WatchLevel::Releases->label());

    expect(Star::count())->toBe(1)->and(Watch::sole()->level)->toBe(WatchLevel::Releases);

    $this->get(route('stars.index'))->assertOk()->assertSee('@moussa/');
    $watch = Watch::sole();
    Livewire::test('pages::community.watching')->call('setLevel', $watch->id, 'ignore');
    expect($watch->fresh()->level)->toBe(WatchLevel::Ignore);

    $this->actingAs($this->author);
    Livewire::test('module-actions', ['moduleId' => $this->module->id])->assertDontSee(__('community.watch.button'));

    app(AccountService::class)->delete($this->hassan, $this->hassan);
    expect(Star::count())->toBe(0)->and(Watch::count())->toBe(0);
});

test('nouvelle version : abonnés, puis utilisateurs non abonnés ; une seule notification ; jamais Ignorer ni l\'auteur', function () {
    $both = User::factory()->create();
    $ignoring = User::factory()->create();
    Watch::create(['user_id' => $this->hassan->id, 'module_id' => $this->module->id, 'level' => WatchLevel::Releases]);
    Watch::create(['user_id' => $both->id, 'module_id' => $this->module->id, 'level' => WatchLevel::All]);
    Watch::create(['user_id' => $ignoring->id, 'module_id' => $this->module->id, 'level' => WatchLevel::Ignore]);
    Watch::create(['user_id' => $this->author->id, 'module_id' => $this->module->id, 'level' => WatchLevel::All]);
    foreach ([$both, $ignoring, $this->awa] as $user) {
        installedBy($this->version, $user);
    }

    publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0'));

    $types = fn (User $user) => Notification::sent($user, ModuleNotification::class)->map(fn ($n) => $n->type)->all();
    expect($types($this->hassan))->toBe([NotificationType::NewVersionWatched])
        ->and($types($both))->toBe([NotificationType::NewVersionWatched])
        ->and($types($this->awa))->toBe([NotificationType::UpdateAvailable])
        ->and($types($ignoring))->toBe([])
        ->and(collect($types($this->author)))->not->toContain(NotificationType::NewVersionWatched);
});

test('dépréciation : abonnés « toutes les activités » et utilisateurs de la version ; archivage : « toutes les activités »', function () {
    Watch::create(['user_id' => $this->hassan->id, 'module_id' => $this->module->id, 'level' => WatchLevel::All]);
    Watch::create(['user_id' => $this->awa->id, 'module_id' => $this->module->id, 'level' => WatchLevel::Releases]);

    app(ModuleLifecycle::class)->deprecate($this->version, 'Double débit possible.', $this->author);
    Notification::assertSentTo($this->hassan, ModuleNotification::class, fn ($n) => $n->type === NotificationType::VersionDeprecated);
    Notification::assertNotSentTo($this->awa, ModuleNotification::class);

    app(ModuleLifecycle::class)->archive($this->module->fresh(), $this->author);
    Notification::assertSentTo($this->hassan, ModuleNotification::class, fn ($n) => $n->type === NotificationType::ModuleArchived);
});

test('S-43 : trois groupes réglables', function () {
    $this->actingAs($this->hassan)->get(route('notifications.edit'))
        ->assertOk()
        ->assertSee(__('notifications.preferences.group_watched'))
        ->assertSee(__('notifications.types.new_version_watched'));
});
