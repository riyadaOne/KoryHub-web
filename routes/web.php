<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ModulePageController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');

Route::post('locale/{locale}', LocaleController::class)->name('locale.update');

// Tableau de bord : accessible avant la vérification de l'e-mail, qui ne bloque que publier et signaler (F-AUT-23).
Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Autoriser un terminal (S-26) : page ouverte par kory login.
    Route::livewire('login/device', 'pages::auth.device')->name('device.show');

    // Espace de l'auteur (W03) : mes modules (S-32), réglages d'un module (S-33).
    Route::livewire('dashboard/modules', 'pages::modules.mine')->name('modules.mine');
    Route::livewire('@{author}/{module}/settings', 'pages::modules.settings')->name('modules.settings');
});

// Fiche d'un kory (S-03 à S-06) : publique ; un module privé répond 404 à tout autre que son auteur.
Route::prefix('@{author}/{module}')->group(function () {
    Route::get('/', [ModulePageController::class, 'show'])->name('modules.show');
    Route::livewire('versions', 'pages::modules.versions')->name('modules.versions');
    Route::get('v/{number}', [ModulePageController::class, 'version'])->name('modules.version');
    Route::get('v/{number}/raw/{path}', [ModulePageController::class, 'raw'])->where('path', '.*')->name('modules.raw');
    Route::get('files', [ModulePageController::class, 'files'])->name('modules.files');
});

require __DIR__.'/settings.php';
