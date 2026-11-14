<?php

use App\Enums\NotificationType;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Moderation\ModerationRefused;
use App\Services\Moderation\ReportService;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Signalements, partie développeur (W07.1, F-MOD-09, F-MOD-11, F-AUT-23, T-17) : feuille
 * « Signaler », S-48, refus, anonymat, alerte unique des signalements multiples.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
    $this->reporter = User::factory()->create(['handle' => 'hassan-djibrine']);
    $this->admin = User::factory()->create(['handle' => 'achta', 'role' => Role::Admin]);
    $this->service = app(ReportService::class);
    $this->version = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/A.php' => "<?php\nfinal class A {}\n"]));
    $this->module = $this->version->module;
});

test('chaque type d\'objet se signale : accusé, audit sans l\'explication, S-48', function () {
    $review = Review::create(['module_id' => $this->module->id, 'user_id' => User::factory()->create()->id, 'rating' => 1, 'comment' => 'Nul.']);
    $targets = [
        [ReportTargetType::Module, $this->module->id],
        [ReportTargetType::Version, $this->version->id],
        [ReportTargetType::User, $this->author->id],
        [ReportTargetType::Review, $review->id],
    ];

    foreach ($targets as [$type, $id]) {
        $report = $this->service->create($this->reporter, $type, $id, ReportReason::Malware, 'Envoie des numéros ailleurs.');
        expect($report->status)->toBe(ReportStatus::Open);
    }

    expect(Report::count())->toBe(4)
        ->and(AuditEvent::where('action', 'report.created')->count())->toBe(4);
    Notification::assertSentToTimes($this->reporter, ModuleNotification::class, 4);
    Notification::assertSentTo($this->reporter, ModuleNotification::class, fn ($n) => $n->type === NotificationType::ReportReceived);

    $this->actingAs($this->reporter)->get(route('reports.mine'))->assertOk()
        ->assertSee('@moussa/paiement 1.0.0')->assertSee(__('moderation.reason.malware'))->assertSee(__('moderation.status.open'));
});

test('refus : e-mail non vérifié, son propre objet, module privé d\'un autre, avis masqué, doublon ouvert', function () {
    $unverified = User::factory()->unverified()->create();
    expect(fn () => $this->service->create($unverified, ReportTargetType::Module, $this->module->id, ReportReason::Malware, null))
        ->toThrow(ModerationRefused::class, __('moderation.errors.email_unverified'));
    expect(fn () => $this->service->create($this->author, ReportTargetType::Module, $this->module->id, ReportReason::Malware, null))
        ->toThrow(ModerationRefused::class, __('moderation.errors.own_target'));

    $private = publishKory($this->author, TarBuilder::kory('@moussa/secret', '1.0.0', ['src/A.php' => "<?php\n"]), 'secret', ['visibility' => 'private']);
    expect(fn () => $this->service->create($this->reporter, ReportTargetType::Module, $private->module_id, ReportReason::Malware, null))
        ->toThrow(ModerationRefused::class, __('moderation.errors.target_not_found'));

    $hidden = Review::create(['module_id' => $this->module->id, 'user_id' => User::factory()->create()->id, 'rating' => 1]);
    $hidden->forceFill(['hidden_at' => now()])->save();
    expect(fn () => $this->service->create($this->reporter, ReportTargetType::Review, $hidden->id, ReportReason::Harassment, null))
        ->toThrow(ModerationRefused::class, __('moderation.errors.target_not_found'));

    $first = $this->service->create($this->reporter, ReportTargetType::Module, $this->module->id, ReportReason::Malware, null);
    expect(fn () => $this->service->create($this->reporter, ReportTargetType::Module, $this->module->id, ReportReason::Copyright, null))
        ->toThrow(ModerationRefused::class, __('moderation.errors.duplicate'));

    // Une fois clos, le même compte peut signaler à nouveau.
    $first->forceFill(['status' => ReportStatus::ClosedNoAction])->save();
    expect($this->service->create($this->reporter, ReportTargetType::Module, $this->module->id, ReportReason::Copyright, null)->exists)->toBeTrue();
});

test('signalements multiples : une seule alerte des admins au seuil, rien de masqué', function () {
    foreach (range(1, 4) as $i) {
        $this->service->create(User::factory()->create(), ReportTargetType::Module, $this->module->id, ReportReason::Malware, null);
    }

    Notification::assertSentToTimes($this->admin, ModuleNotification::class, 1);
    Notification::assertSentTo($this->admin, ModuleNotification::class, fn ($n) => $n->type === NotificationType::AdminReportsMultiple && $n->params['count'] === 3);
    $this->get(route('modules.show', ['author' => 'moussa', 'module' => 'paiement']))->assertOk();
});

test('feuille « Signaler » : envoi, doublon indiqué, absente sur ses propres objets, visiteur vers la connexion', function () {
    Livewire::actingAs($this->reporter)->test('report-button', ['type' => 'version', 'targetId' => $this->version->id])
        ->assertSee(__('moderation.report'))
        ->set('reason', 'copyright')->set('details', 'Copié de mpesa-stk.')
        ->call('send')
        ->assertSet('sent', true)
        ->assertHasNoErrors();
    expect(Report::sole()->reason)->toBe(ReportReason::Copyright);

    Livewire::actingAs($this->reporter)->test('report-button', ['type' => 'version', 'targetId' => $this->version->id])
        ->assertSee('data-test="report-duplicate"', false);

    Livewire::actingAs($this->author)->test('report-button', ['type' => 'module', 'targetId' => $this->module->id])
        ->assertDontSee(__('moderation.report'));

    auth()->logout();
    Livewire::test('report-button', ['type' => 'module', 'targetId' => $this->module->id])
        ->call('openSheet')->assertRedirect(route('login'));
});

test('la personne visée ne voit jamais qui l\'a signalée', function () {
    $this->service->create($this->reporter, ReportTargetType::Module, $this->module->id, ReportReason::Malware, 'Détail privé.');

    Notification::assertNotSentTo($this->author, ModuleNotification::class, fn ($n) => $n->type->group() === 'reports' || $n->type === NotificationType::AdminReportsMultiple);
    $this->actingAs($this->author)->get(route('modules.show', ['author' => 'moussa', 'module' => 'paiement']))
        ->assertOk()->assertDontSee('hassan-djibrine')->assertDontSee('Détail privé.');
    $this->actingAs($this->author)->get(route('reports.mine'))->assertOk()->assertSee('data-test="reports-empty"', false);
    $this->actingAs($this->author)->getJson('http://'.config('koryhub.domains.api').'/v1/modules/@moussa/paiement')->assertDontSee('hassan-djibrine');
});
