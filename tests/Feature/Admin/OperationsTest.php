<?php

use App\Enums\BackupKind;
use App\Enums\BackupStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Exceptions\InvalidTransition;
use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\ModuleNotification;
use App\Services\Operations\BackupService;
use App\Services\Operations\DatabaseBackup;
use App\Services\Operations\WeeklyReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
 * Sauvegardes, supervision et rapport du lundi (W08.5, W08.6, F-ADM-08 à F-ADM-10, T-36, T-37) :
 * backup_runs, conservation, échec signalé, test de restauration, signal de vie, S-72, rapport.
 * La vraie sauvegarde (mysqldump) est remplacée par un faux ; elle se vérifie en recette.
 */

beforeEach(function () {
    Notification::fake();
    Http::fake();
    $this->fake = new class implements DatabaseBackup
    {
        public bool $fail = false;

        public array $deleted = [];

        public array $restored = [];

        public function dump(BackupKind $kind): array
        {
            if ($this->fail) {
                throw new RuntimeException('mysqldump : accès refusé');
            }

            return ['location' => 'koryhub/'.$kind->value.'-'.uniqid().'.zip', 'size' => 18_000_000];
        }

        public function restore(string $location, string $database): array
        {
            $this->restored[] = [$location, $database];

            return ['tables' => 14, 'rows' => 1200];
        }

        public function delete(string $location): void
        {
            $this->deleted[] = $location;
        }
    };
    $this->app->instance(DatabaseBackup::class, $this->fake);
    $this->admin = User::factory()->create(['handle' => 'achta', 'role' => Role::Admin]);
    $this->admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
    $this->backups = app(BackupService::class);
});

test('sauvegarde réussie : notée, signal envoyé ; terminée, elle ne repasse jamais en cours', function () {
    config(['koryhub.healthchecks.backup' => 'https://hc-ping.com/backup']);

    $run = $this->backups->run(BackupKind::Daily);

    expect($run->status)->toBe(BackupStatus::Success)->and($run->size)->toBe(18_000_000)->and($run->location)->toStartWith('koryhub/daily-');
    Http::assertSent(fn ($request) => $request->url() === 'https://hc-ping.com/backup');
    expect(fn () => $run->finish(BackupStatus::Running))->toThrow(InvalidTransition::class)
        ->and(fn () => $run->finish(BackupStatus::Failed))->toThrow(InvalidTransition::class);
});

test('conservation : 30 quotidiennes, 12 mensuelles ; les lignes restent', function () {
    foreach (range(1, 31) as $day) {
        $this->travel(1)->days();
        $this->backups->run(BackupKind::Daily);
    }
    $this->backups->run(BackupKind::Monthly);

    expect($this->fake->deleted)->toHaveCount(1)
        ->and(BackupRun::where('kind', BackupKind::Daily)->count())->toBe(32 - 1)
        ->and(BackupRun::where('kind', BackupKind::Daily)->whereNotNull('location')->count())->toBe(30)
        ->and(BackupRun::where('kind', BackupKind::Monthly)->whereNotNull('location')->count())->toBe(1);
});

test('échec : noté avec sa cause, admins alertés par e-mail même s\'ils l\'ont coupé, signal « fail »', function () {
    config(['koryhub.healthchecks.backup' => 'https://hc-ping.com/backup']);
    $this->admin->notificationPreferences()->create(['type' => NotificationType::AdminIncident, 'mail' => false, 'database' => false]);
    $this->fake->fail = true;

    $run = $this->backups->run(BackupKind::Daily);

    expect($run->status)->toBe(BackupStatus::Failed)->and($run->note)->toBe('mysqldump : accès refusé');
    Notification::assertSentTo($this->admin->fresh(), ModuleNotification::class, fn ($n, $channels) => $n->type === NotificationType::AdminIncident && $channels === ['mail']);
    Http::assertSent(fn ($request) => $request->url() === 'https://hc-ping.com/backup/fail');
    $this->artisan('koryhub:backup --kind=daily')->assertFailed();
    $this->artisan('koryhub:backup --kind=weekly')->assertExitCode(2);
});

test('test de restauration : sans sauvegarde, échec ; sinon dans la base de test, jamais celle de l\'application', function () {
    expect($this->backups->restoreTest()->status)->toBe(BackupStatus::Failed);

    $backup = $this->backups->run(BackupKind::Daily);
    $run = $this->backups->restoreTest();

    expect($run->status)->toBe(BackupStatus::Success)
        ->and($run->note)->toBe(__('admin.health.restore_note', ['tables' => 14, 'rows' => 1200]))
        ->and($this->fake->restored)->toBe([[$backup->location, 'koryhub_restore']]);
});

test('signal de vie : noté pour S-72 ; rien n\'est envoyé sans adresse', function () {
    $this->backups->heartbeat();
    Http::assertNothingSent();
    expect(Cache::get(BackupService::HEARTBEAT_KEY))->not->toBeNull();

    config(['koryhub.healthchecks.heartbeat' => 'https://hc-ping.com/beat']);
    $this->artisan('koryhub:heartbeat')->assertSuccessful();
    Http::assertSent(fn ($request) => $request->url() === 'https://hc-ping.com/beat');
});

test('S-72 et S-60 : signal, sauvegardes, restauration, liste', function () {
    $this->backups->heartbeat();
    $this->backups->run(BackupKind::Daily);
    $this->fake->fail = true;
    $this->travel(1)->minutes();
    $this->backups->run(BackupKind::Monthly);

    $admin = 'http://'.config('koryhub.domains.admin');
    $this->actingAs($this->admin)->get($admin.'/health')->assertOk()
        ->assertSee('data-test="tile-scheduler" data-ok="yes"', false)
        ->assertSee('data-test="tile-backup" data-ok="no"', false)
        ->assertSee('mysqldump : accès refusé')->assertSee(__('admin.health.kind.daily'))
        ->assertSee('data-test="healthchecks-off"', false);
    $this->get($admin.'/')->assertOk()->assertSee('data-test="health-card"', false);
});

test('rapport du lundi : semaine écoulée, envoyé même sans activité, disponibilité non mesurée', function () {
    $this->travelTo(now()->next('Monday')->setTime(8, 0));
    $report = app(WeeklyReport::class)->build();
    expect($report['to'])->toBe(now()->subDay()->toDateString())
        ->and($report['backups_ok'])->toBe(0)->and($report['restore'])->toBe('');

    expect(app(WeeklyReport::class)->send())->toBe(1);
    Notification::assertSentTo($this->admin, ModuleNotification::class, function ($n) {
        $mail = implode("\n", $n->toMail($this->admin)->introLines);

        return $n->type === NotificationType::AdminWeeklyReport
            && str_contains($mail, __('notifications.admin_weekly_report.uptime'))
            && str_contains($mail, __('notifications.admin_weekly_report.restore_none'));
    });
});
