<?php

use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ModulePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DiscoverController::class, 'home'])->name('home');

// Pages publiques (S-10 à S-12, W09) et scripts d'installation du CLI (K05, TRD §18.1).
Route::get('governance', [DocsController::class, 'publicPage'])->defaults('page', 'governance')->name('governance');
Route::get('terms', [DocsController::class, 'publicPage'])->defaults('page', 'terms')->name('terms');
Route::get('privacy', [DocsController::class, 'publicPage'])->defaults('page', 'privacy')->name('privacy');
Route::get('install.ps1', [InstallController::class, 'powershell'])->name('install.windows');
Route::get('install.sh', [InstallController::class, 'shell'])->name('install.unix');

Route::post('locale/{locale}', LocaleController::class)->name('locale.update');

// Découverte (W05) : catégorie (S-09), profil public (S-08).
Route::get('categories/{slug}', [DiscoverController::class, 'category'])->name('categories.show');
Route::get('@{author}', [DiscoverController::class, 'profile'])->where('author', '[a-z0-9-]+')->name('profiles.show');

// Recherche (S-02, W05) : publique.
Route::livewire('search', 'pages::search')->name('search');

// Tableau de bord : accessible avant la vérification de l'e-mail, qui ne bloque que publier et signaler (F-AUT-23).
Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Autoriser un terminal (S-26) : page ouverte par kory login.
    Route::livewire('login/device', 'pages::auth.device')->name('device.show');

    // Espace de l'auteur (W03) : mes modules (S-32), réglages d'un module (S-33).
    Route::livewire('dashboard/modules', 'pages::modules.mine')->name('modules.mine');
    Route::livewire('@{author}/{module}/settings', 'pages::modules.settings')->name('modules.settings');

    // Communauté (W05) : favoris (S-44), modules suivis (S-45).
    Route::livewire('stars', 'pages::community.stars')->name('stars.index');
    Route::livewire('watching', 'pages::community.watching')->name('watching.index');

    // Toutes les notifications (S-31, W08).
    Route::livewire('notifications', 'pages::notifications')->name('notifications.index');

    // Certification (W06) : demande (S-35), mes dossiers (S-36).
    Route::livewire('@{author}/{module}/v/{number}/certification', 'pages::certification.request')->name('modules.certification');
    Route::livewire('dashboard/certifications', 'pages::certification.index')->name('certifications.index');
    Route::livewire('dashboard/certifications/{certification}', 'pages::certification.show')->name('certifications.show');
});

// Fiche d'un kory (S-03 à S-06) : publique ; un module privé répond 404 à tout autre que son auteur.
Route::prefix('@{author}/{module}')->group(function () {
    Route::get('/', [ModulePageController::class, 'show'])->name('modules.show');
    Route::livewire('versions', 'pages::modules.versions')->name('modules.versions');
    Route::get('v/{number}', [ModulePageController::class, 'version'])->name('modules.version');
    Route::get('v/{number}/raw/{path}', [ModulePageController::class, 'raw'])->where('path', '.*')->name('modules.raw');
    Route::get('v/{number}/certification-report/{locale}', [ModulePageController::class, 'report'])->where('locale', 'en|fr|ar|pt')->name('modules.report');
    Route::get('files', [ModulePageController::class, 'files'])->name('modules.files');
    Route::get('architecture', [ModulePageController::class, 'architecture'])->name('modules.architecture');
    Route::livewire('reviews', 'pages::modules.reviews')->name('modules.reviews');
});

require __DIR__.'/settings.php';
