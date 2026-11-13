<?php

use App\Enums\ModuleStatus;
use App\Enums\VersionStatus;
use App\Enums\Visibility;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\DemoModulesSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * Kory de démonstration (W03.10) : publiés par le vrai service, avec architecture et cycle de vie.
 */

test('les kory de démonstration sont publiés avec leur architecture, des versions dépréciées, un privé et un archivé', function () {
    Storage::fake('local');
    foreach (['moussa', 'hassan-djibrine', 'fatime-zakaria'] as $handle) {
        User::factory()->create(['handle' => $handle]);
    }

    $this->seed(DemoModulesSeeder::class);

    $mobileMoney = Module::where('name', 'mobile-money-resilient')->sole();
    expect($mobileMoney->latestVersion->version)->toBe('1.2.0')
        ->and($mobileMoney->latestVersion->components()->count())->toBe(8)
        ->and($mobileMoney->latestVersion->links()->count())->toBe(9)
        ->and($mobileMoney->versions()->where('status', VersionStatus::Deprecated)->count())->toBe(2)
        ->and(Module::where('name', 'recu-qr')->sole()->visibility)->toBe(Visibility::Private)
        ->and(Module::where('name', 'sms-ussd-fallback')->sole()->status)->toBe(ModuleStatus::Archived)
        ->and($mobileMoney->fresh()->download_count)->toBe(9)
        ->and($mobileMoney->fresh()->third_party_downloaded_at)->not->toBeNull()
        ->and(User::where('handle', 'hassan-djibrine')->sole()->moduleDownloads()->count())->toBe(2);

    $this->get('/@moussa/mobile-money-resilient/architecture')->assertOk()->assertSee('RetryPendingPayments');
});
