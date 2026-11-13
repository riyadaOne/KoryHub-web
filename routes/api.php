<?php

use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\DeviceAuthorizationController;
use App\Http\Controllers\Api\V1\DownloadController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VersionController;
use App\Http\Middleware\AuthenticateIfToken;
use App\Http\Middleware\EnsureApiAccountActive;
use Illuminate\Support\Facades\Route;

/*
 * API du CLI : api.koryhub.dev/v1 (TRD §17). Enregistrée dans bootstrap/app.php,
 * sur le domaine de l'API, avec le préfixe v1 et le groupe de middlewares « api ».
 * Une route ou un champ ajoutés oui ; retirés ou renommés non (T-07).
 */

Route::get('/', fn () => response()->json([
    'name' => config('app.name').' API',
    'version' => 'v1',
]))->name('index');

Route::get('meta', MetaController::class)->name('meta');

/*
 * Connexion du CLI (TRD §7.3) : demande d'un code et interrogation, sans jeton ; limites
 * par adresse IP. Puis routes authentifiées par le jeton (Sanctum, TRD §7.2).
 */
Route::post('auth/device', [DeviceAuthorizationController::class, 'store'])
    ->middleware('throttle:10,1')->name('auth.device');
Route::post('auth/device/token', [DeviceAuthorizationController::class, 'token'])
    ->middleware('throttle:30,1')->name('auth.device.token');

Route::middleware(['auth:sanctum', EnsureApiAccountActive::class, 'throttle:120,1'])->group(function () {
    Route::get('user', [UserController::class, 'show'])->name('user');
    Route::delete('auth/token', [TokenController::class, 'destroy'])->name('auth.token.destroy');

    // Publication (W03) : kory push.
    Route::post('modules/{author}/{module}/versions', [VersionController::class, 'store'])
        ->middleware('throttle:'.config('koryhub.publication.per_hour').',60')
        ->name('modules.versions.store');
});

/*
 * Téléchargement et intégration (W04) : ouverts sans compte (F-RCH-15) ; un jeton, s'il est
 * envoyé, doit être valable. La limite des téléchargements est appliquée par DownloadService.
 */
Route::middleware([AuthenticateIfToken::class, EnsureApiAccountActive::class, 'throttle:120,1'])->group(function () {
    Route::get('modules/{author}/{module}', [ModuleController::class, 'show'])->name('modules.show');
    Route::get('modules/{author}/{module}/versions/{version}', [ModuleController::class, 'version'])->name('modules.versions.show');
    Route::get('modules/{author}/{module}/versions/{version}/download', DownloadController::class)->name('modules.versions.download');
    Route::post('audit', AuditController::class)->middleware('throttle:30,1')->name('audit');
    Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');
});
