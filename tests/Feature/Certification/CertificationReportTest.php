<?php

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\WatchLevel;
use App\Jobs\GenerateCertificationReport;
use App\Models\Certification;
use App\Models\CertificationReport;
use App\Models\ModuleDownload;
use App\Models\User;
use App\Models\Watch;
use App\Notifications\ModuleNotification;
use App\Services\Certification\CertificationService;
use App\Services\Certification\Reports\ReportRenderer;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Rapport de certification et notifications (W06.3, F-MOD-06, F-MOD-14, T-35, UXUI §11) :
 * deux PDF (langue du développeur et anglais), jamais régénérés, lien sur S-05, audience.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);

    // Moteur factice : pas de Chrome dans les tests ; garde le HTML rendu pour l'inspecter.
    $this->renderer = new class implements ReportRenderer
    {
        public array $html = [];

        public bool $fail = false;

        public function render(string $view, array $data): string
        {
            if ($this->fail) {
                throw new RuntimeException('chrome indisponible');
            }
            $html = view($view, $data)->render();
            $this->html[app()->getLocale()] = $html;

            return '%PDF-1.7 '.app()->getLocale().' '.strlen($html);
        }
    };
    $this->app->instance(ReportRenderer::class, $this->renderer);

    $this->author = User::factory()->create(['handle' => 'moussa', 'locale' => 'fr']);
    $this->admin = User::factory()->create(['handle' => 'achta', 'name' => 'Achta Abakar', 'role' => Role::Admin]);
    $this->service = app(CertificationService::class);
    $this->version = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/A.php' => "<?php\nfinal class A {}\n"], [
        'architecture' => ['components' => [['id' => 'A', 'file' => 'src/A.php', 'kind' => 'class']], 'links' => []],
    ], "# P\n\n## Problème résolu\n\nx\n\n## Pièges évités\n\n1. y\n"));
});

function grantNow(object $test, User $author): Certification
{
    $certification = $test->service->request($test->version, $author);
    $test->service->take($certification, $test->admin);
    $test->service->grant($certification->fresh(), $test->admin, 'Interface claire.', 'README complet.');

    return $certification->fresh();
}

test('accord : deux rapports (français et anglais), empreinte, contenu sans nom d\'admin, liens sur S-05', function () {
    $certification = grantNow($this, $this->author);

    $reports = CertificationReport::query()->where('certification_id', $certification->id)->pluck('locale')->sort()->values()->all();
    expect($reports)->toBe(['en', 'fr']);

    $fr = CertificationReport::where('locale', 'fr')->sole();
    expect($fr->sha256)->toBe(hash('sha256', Storage::disk('local')->get($fr->path)))
        ->and($this->renderer->html['fr'])->toContain('Rapport de certification')
        ->toContain($this->version->archive_sha256)
        ->toContain('Interface claire.')
        ->not->toContain('Achta')
        ->and($this->renderer->html['en'])->toContain('Certification report');

    $page = route('modules.version', ['author' => 'moussa', 'module' => 'paiement', 'number' => '1.0.0']);
    $this->get($page)->assertSee('data-test="report-fr"', false)->assertSee('data-test="report-en"', false);
    $this->get(route('modules.report', ['author' => 'moussa', 'module' => 'paiement', 'number' => '1.0.0', 'locale' => 'fr']))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('jamais régénéré ; révocation : le rapport reste, S-05 l\'indique', function () {
    $certification = grantNow($this, $this->author);
    $before = CertificationReport::where('locale', 'fr')->sole();

    (new GenerateCertificationReport($certification->id, 'fr'))->handle($this->renderer);
    expect(CertificationReport::where('locale', 'fr')->count())->toBe(1)
        ->and(CertificationReport::where('locale', 'fr')->sole()->sha256)->toBe($before->sha256);

    $this->service->revoke($certification, $this->admin, 'Faille signalée.');
    $this->get(route('modules.version', ['author' => 'moussa', 'module' => 'paiement', 'number' => '1.0.0']))
        ->assertSee('data-test="certification-revoked"', false)->assertSee('data-test="report-fr"', false);
    $this->get(route('modules.report', ['author' => 'moussa', 'module' => 'paiement', 'number' => '1.0.0', 'locale' => 'fr']))->assertOk();
});

test('développeur arabophone : rapport arabe de droite à gauche et anglais', function () {
    $this->author->forceFill(['locale' => 'ar'])->save();
    grantNow($this, $this->author->fresh());

    expect(CertificationReport::pluck('locale')->sort()->values()->all())->toBe(['ar', 'en'])
        ->and($this->renderer->html['ar'])->toContain('dir="rtl"')->toContain('تقرير الاعتماد');
});

test('échec de génération : aucune ligne ; rapport d\'une certification refusée : 404', function () {
    $this->renderer->fail = true;
    expect(fn () => grantNow($this, $this->author))->toThrow(RuntimeException::class);
    expect(CertificationReport::count())->toBe(0);

    $this->get(route('modules.report', ['author' => 'moussa', 'module' => 'paiement', 'number' => '1.0.0', 'locale' => 'fr']))->assertNotFound();
});

test('audience prévenue de l\'accord et de la révocation : abonnés et utilisateurs, jamais « Ignorer »', function () {
    $watcher = User::factory()->create();
    $user = User::factory()->create();
    $ignoring = User::factory()->create();
    Watch::create(['user_id' => $watcher->id, 'module_id' => $this->version->module_id, 'level' => WatchLevel::Releases]);
    Watch::create(['user_id' => $ignoring->id, 'module_id' => $this->version->module_id, 'level' => WatchLevel::Ignore]);
    foreach ([$user, $ignoring] as $u) {
        ModuleDownload::create(['user_id' => $u->id, 'module_id' => $this->version->module_id, 'version_id' => $this->version->id, 'first_at' => now(), 'last_at' => now()]);
    }

    $certification = grantNow($this, $this->author);
    $this->service->revoke($certification, $this->admin, 'Faille.');

    foreach ([$watcher, $user] as $u) {
        $types = Notification::sent($u, ModuleNotification::class)->map(fn ($n) => $n->type)->all();
        expect($types)->toBe([NotificationType::CertificationGranted, NotificationType::CertificationRevoked]);
    }
    Notification::assertNotSentTo($ignoring, ModuleNotification::class);
});
