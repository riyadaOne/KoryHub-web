<?php

use App\Enums\NotificationType;
use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Enums\Role;
use App\Enums\VersionStatus;
use App\Enums\WatchLevel;
use App\Exceptions\InvalidTransition;
use App\Models\AuditEvent;
use App\Models\ModuleDownload;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Models\Watch;
use App\Notifications\ModuleNotification;
use App\Services\Certification\PreliminaryChecks;
use App\Services\Community\ReviewService;
use App\Services\Moderation\ModerationRefused;
use App\Services\Moderation\ModerationService;
use App\Services\Moderation\ReportService;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Modération par l'admin (W07.2, F-MOD-10 à F-MOD-15, F-RCH-13, T-13) : S-61, S-62, clôture de
 * tous les signalements d'un objet, avertissement de sécurité, retrait légal, avis masqué, relance.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    $this->admin = User::factory()->create(['handle' => 'achta', 'role' => Role::Admin]);
    $this->admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    $this->adminUrl = 'http://'.config('koryhub.domains.admin');
    $this->moderation = app(ModerationService::class);
    $this->reports = app(ReportService::class);
    $this->version = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/A.php' => "<?php\nfinal class A {}\n"]));
    $this->reporters = User::factory()->count(2)->create();
    $this->report = $this->reports->create($this->reporters[0], ReportTargetType::Version, $this->version->id, ReportReason::Malware, 'Faux paiements.');
    $this->reports->create($this->reporters[1], ReportTargetType::Version, $this->version->id, ReportReason::Malware, null);
});

test('S-61 et S-62 : réservés aux admins ; l\'admin voit qui a signalé', function () {
    $this->actingAs($this->author)->get($this->adminUrl.'/reports')->assertForbidden();

    $this->actingAs($this->admin)->get($this->adminUrl.'/')->assertOk()->assertSee(trans_choice('moderation.admin.open_reports', 2, ['count' => 2]));
    $this->get($this->adminUrl.'/reports')->assertOk()->assertSee('@moussa/paiement 1.0.0');
    $this->get($this->adminUrl.'/reports/'.$this->report->id)->assertOk()
        ->assertSee('@'.$this->reporters[0]->handle)->assertSee('Faux paiements.')->assertSee('src/A.php');
});

test('clore sans suite : tous les signalements de l\'objet, un résultat par auteur, motif obligatoire, jamais rouvert', function () {
    expect(fn () => $this->moderation->closeNoAction($this->report, $this->admin, ' '))->toThrow(ModerationRefused::class, __('moderation.errors.reason_required'));
    expect(fn () => $this->moderation->closeNoAction($this->report, $this->author, 'Non.'))->toThrow(ModerationRefused::class);

    $this->moderation->take($this->report, $this->admin);
    $this->moderation->closeNoAction($this->report->fresh(), $this->admin, 'Code conforme.');

    expect(Report::where('status', ReportStatus::ClosedNoAction)->count())->toBe(2)
        ->and(AuditEvent::where('action', 'report.status_changed')->where('reason', 'Code conforme.')->count())->toBe(2);
    foreach ($this->reporters as $reporter) {
        Notification::assertSentTo($reporter, ModuleNotification::class, fn ($n) => $n->type === NotificationType::ReportClosed);
    }
    expect(fn () => $this->moderation->take($this->report->fresh(), $this->admin))->toThrow(InvalidTransition::class);

    $this->actingAs($this->reporters[0])->get(route('reports.mine'))->assertSee(__('moderation.mine.no_action'))->assertDontSee('Code conforme.');
});

test('avertissement de sécurité : bandeau, audience prévenue, at_risk dans l\'audit du CLI, certification bloquée, levée sans notification', function () {
    $watcher = User::factory()->create();
    $user = User::factory()->create();
    Watch::create(['user_id' => $watcher->id, 'module_id' => $this->version->module_id, 'level' => WatchLevel::Releases]);
    ModuleDownload::create(['user_id' => $user->id, 'module_id' => $this->version->module_id, 'version_id' => $this->version->id, 'first_at' => now(), 'last_at' => now()]);

    $this->moderation->markAtRisk($this->version, $this->admin, 'Webhook non signé.', $this->report);

    expect($this->version->fresh()->at_risk_message)->toBe('Webhook non signé.')
        ->and(Report::where('resolution', ReportResolution::SecurityWarning)->count())->toBe(2);
    foreach ([$this->author, $watcher, $user] as $recipient) {
        Notification::assertSentTo($recipient, ModuleNotification::class, fn ($n) => $n->type === NotificationType::VersionAtRisk);
    }
    $this->get(route('modules.version', ['author' => 'moussa', 'module' => 'paiement', 'number' => '1.0.0']))
        ->assertSee('data-test="at-risk-banner"', false)->assertSee('Webhook non signé.');
    $result = $this->postJson('http://'.config('koryhub.domains.api').'/v1/audit', ['modules' => [['name' => '@moussa/paiement', 'version' => '1.0.0']]])->json('results.0');
    $this->getJson('http://'.config('koryhub.domains.api').'/v1/modules/moussa/paiement/versions/1.0.0')->assertJsonPath('at_risk_message', 'Webhook non signé.');
    expect($result['state'])->toBe('at_risk')->and($result['message'])->toBe('Webhook non signé.')
        ->and(collect(app(PreliminaryChecks::class)->run($this->version->fresh()))->firstWhere('code', 'version_published')['passed'])->toBeFalse();

    Notification::fake();
    $this->moderation->clearAtRisk($this->version->fresh(), $this->admin, 'Corrigé en 1.0.1.');
    expect($this->version->fresh()->at_risk_at)->toBeNull();
    Notification::assertNothingSent();
});

test('retrait légal : mot de passe redemandé, archive et README supprimés, empreinte gardée, 410, jamais republiée', function () {
    $path = $this->version->archive_path;
    $sha = $this->version->archive_sha256;
    Livewire::actingAs($this->admin)->test('pages::admin.report', ['report' => $this->report])
        ->call('open', 'remove')->assertRedirect(route('password.confirm'));

    session(['auth.password_confirmed_at' => time()]);
    Livewire::actingAs($this->admin)->test('pages::admin.report', ['report' => $this->report])
        ->set('reason', 'Demande de retrait reçue le 12 novembre.')->set('publicMessage', 'Retirée pour une raison légale.')
        ->call('remove')->assertHasNoErrors()->assertSee(__('moderation.admin.removed_done'));

    $version = $this->version->fresh();
    expect($version->status)->toBe(VersionStatus::Removed)
        ->and($version->archive_sha256)->toBe($sha)
        ->and($version->readme)->toBeNull()
        ->and($version->removed_by)->toBe($this->admin->id)
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(AuditEvent::where('action', 'version.removed')->where('reason', 'Demande de retrait reçue le 12 novembre.')->exists())->toBeTrue();
    Notification::assertSentTo($this->author, ModuleNotification::class, fn ($n) => $n->type === NotificationType::VersionRemoved);

    $this->get(route('modules.version', ['author' => 'moussa', 'module' => 'paiement', 'number' => '1.0.0']))
        ->assertSee('data-test="removed-banner"', false)->assertSee('Retirée pour une raison légale.')->assertDontSee('Demande de retrait')->assertDontSee('data-test="install-version"', false);
    $this->get('http://'.config('koryhub.domains.api').'/v1/modules/moussa/paiement/versions/1.0.0/download')->assertStatus(410);
    $this->get(route('modules.show', ['author' => 'moussa', 'module' => 'paiement']))->assertOk();
    expect(fn () => $this->moderation->removeVersion($version, $this->admin, 'x', 'y'))->toThrow(InvalidTransition::class);
});

test('avis masqué : absent de S-07 et de la note, son auteur le voit encore ; réaffiché', function () {
    $reviewer = User::factory()->create();
    ModuleDownload::create(['user_id' => $reviewer->id, 'module_id' => $this->version->module_id, 'version_id' => $this->version->id, 'first_at' => now(), 'last_at' => now()]);
    $review = Review::create(['module_id' => $this->version->module_id, 'user_id' => $reviewer->id, 'rating' => 1, 'comment' => 'Arnaque totale.']);
    app(ReviewService::class)->refreshRating($this->version->module);
    $report = $this->reports->create($this->reporters[0], ReportTargetType::Review, $review->id, ReportReason::Harassment, null);

    $this->moderation->hideReview($review, $this->admin, 'Propos injurieux.', $report);

    $module = $this->version->module->fresh();
    expect($module->rating_count)->toBe(0)->and($report->fresh()->resolution)->toBe(ReportResolution::ReviewHidden);
    $page = route('modules.reviews', ['author' => 'moussa', 'module' => 'paiement']);
    $this->get($page)->assertDontSee('Arnaque totale.');
    $this->actingAs($reviewer)->get($page)->assertSee('data-test="review-hidden-mine"', false);

    $this->moderation->showReview($review->fresh(), $this->admin, 'Erreur.');
    expect($module->fresh()->rating_count)->toBe(1);
});

test('module devenu privé : S-62 ne montre rien (T-13) ; relance des signalements de plus de 48 h', function () {
    $this->version->module->forceFill(['visibility' => 'private'])->save();
    $this->actingAs($this->admin)->get($this->adminUrl.'/reports/'.$this->report->id)->assertOk()
        ->assertSee('data-test="private-target"', false)->assertDontSee('src/A.php')->assertDontSee('data-test="remove"', false);

    $this->travel(49)->hours();
    expect($this->moderation->remindStale())->toBe(2)->and($this->moderation->remindStale())->toBe(0);
    Notification::assertSentTo($this->admin, ModuleNotification::class, fn ($n) => $n->type === NotificationType::AdminReportStale);
});
