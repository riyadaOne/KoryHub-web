<?php

use App\Enums\Role;
use App\Models\Certification;
use App\Models\Module;
use App\Models\Report;
use App\Models\User;
use App\Services\Certification\Reports\ReportRenderer;
use Database\Seeders\ShowcaseSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Testing\Fakes\MailFake;
use Illuminate\Support\Testing\Fakes\NotificationFake;

/*
 * Jeu vitrine de la démo en ligne (DON-09) : comptes réels « boite+compte@domaine », sans admin créé.
 */

beforeEach(function () {
    Storage::fake('local');
    config(['koryhub.demo.password' => 'mot-de-passe-de-test', 'koryhub.demo.showcase_mailbox' => 'boite@example.com']);
    $this->app->instance(ReportRenderer::class, new class implements ReportRenderer
    {
        public function render(string $view, array $data): string
        {
            return '%PDF-demo';
        }
    });
});

test('le jeu vitrine crée huit comptes vérifiés aux adresses réelles, les kory, avis, signalements et jetons, sans admin ni e-mail', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->seed(ShowcaseSeeder::class);

    $accounts = User::query()->whereKeyNot($admin->id)->get();
    expect($accounts)->toHaveCount(8)
        ->and($accounts->whereNull('email_verified_at'))->toHaveCount(0)
        ->and($accounts->firstWhere('handle', 'moussa')->email)->toBe('boite+moussa@example.com')
        ->and(User::query()->where('role', Role::Admin)->count())->toBe(1)
        ->and(User::query()->where('handle', 'moussa')->sole()->tokens()->count())->toBe(5)
        ->and(Module::query()->where('name', 'recu-qr')->exists())->toBeTrue()
        ->and(Module::query()->where('name', 'offline-sync')->sole()->reviews()->count())->toBe(3)
        ->and(Certification::query()->count())->toBeGreaterThan(0)
        ->and(Report::query()->count())->toBeGreaterThan(0);

    // Les notifications du seed sont captées, jamais envoyées.
    expect(Notification::getFacadeRoot())->toBeInstanceOf(NotificationFake::class)
        ->and(Mail::getFacadeRoot())->toBeInstanceOf(MailFake::class);
});

test('le jeu vitrine refuse de tourner sans admin réel', function () {
    $this->seed(ShowcaseSeeder::class);
})->throws(RuntimeException::class, 'koryhub:admin');

test('le jeu vitrine refuse une boîte absente ou déjà déclinée', function (?string $mailbox) {
    User::factory()->create(['role' => Role::Admin]);
    config(['koryhub.demo.showcase_mailbox' => $mailbox]);

    $this->seed(ShowcaseSeeder::class);
})->with([null, 'pas-une-adresse', 'boite+x@example.com'])->throws(RuntimeException::class, 'KORYHUB_SHOWCASE_MAILBOX');
