<?php

use App\Enums\ModuleStatus;
use App\Enums\Role;
use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Models\Certification;
use App\Models\Module;
use App\Models\User;
use App\Services\Certification\Reports\ReportRenderer;
use Database\Seeders\DemoModulesSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * Kory de démonstration (W03.10) : publiés par le vrai service, avec architecture et cycle de vie.
 */

test('les kory de démonstration sont publiés avec leur architecture, des versions dépréciées, un privé et un archivé', function () {
    Storage::fake('local');
    foreach (['moussa', 'hassan-djibrine', 'fatime-zakaria', 'awa-diop', 'wanjiru'] as $handle) {
        User::factory()->create(['handle' => $handle]);
    }

    User::factory()->create(['handle' => 'achta-riyadaone', 'role' => Role::Admin]);
    $this->app->instance(ReportRenderer::class, new class implements ReportRenderer
    {
        public function render(string $view, array $data): string
        {
            return '%PDF-demo';
        }
    });

    $this->seed(DemoModulesSeeder::class);

    $mobileMoney = Module::where('name', 'mobile-money-resilient')->sole();
    expect($mobileMoney->latestVersion->version)->toBe('1.2.0')
        ->and($mobileMoney->latestVersion->components()->count())->toBe(8)
        ->and($mobileMoney->latestVersion->links()->count())->toBe(9)
        ->and($mobileMoney->versions()->where('status', VersionStatus::Deprecated)->count())->toBe(2)
        ->and(Module::where('name', 'recu-qr')->sole()->visibility)->toBe(Visibility::Private)
        ->and(Module::where('name', 'sms-ussd-fallback')->sole()->status)->toBe(ModuleStatus::Archived)
        ->and($mobileMoney->fresh()->download_count)->toBe(11)
        ->and($mobileMoney->fresh()->rating_count)->toBe(3)
        ->and($mobileMoney->reviews()->count())->toBe(4)
        ->and($mobileMoney->fresh()->is_featured)->toBeTrue()
        ->and($mobileMoney->fresh()->score)->toBeGreaterThan(0.0)
        ->and($mobileMoney->stars()->count())->toBe(3)
        ->and($mobileMoney->fresh()->third_party_downloaded_at)->not->toBeNull()
        ->and(User::where('handle', 'hassan-djibrine')->sole()->moduleDownloads()->count())->toBe(2);

    // Un dossier de certification dans chaque statut (W06).
    expect(Certification::query()->pluck('status')->map->value->sort()->values()->all())
        ->toBe(['complement_required', 'granted', 'in_audit', 'refused', 'requested', 'revoked'])
        ->and($mobileMoney->latestVersion->fresh()->isCertified())->toBeTrue();

    $this->get('/@moussa/mobile-money-resilient/architecture')->assertOk()->assertSee('RetryPendingPayments');
});
