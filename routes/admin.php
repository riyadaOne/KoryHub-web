<?php

use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

/*
 * Administration : admin.koryhub.dev (TRD §6). Enregistrée avant le site principal
 * et Fortify (piège 2). Rôle admin et double authentification obligatoire ajoutés en W06.
 */

// Rôle admin et double authentification obligatoires (EnsureAdmin, F-AUT-22) ; un développeur reçoit 403.
Route::middleware(['auth', 'verified', EnsureAdmin::class])->group(function () {
    Route::view('/', 'admin.home')->name('home');

    // Certification (W06.2) : liste (S-63), dossier (S-64).
    Route::livewire('certifications', 'pages::admin.certifications')->name('certifications.index');
    Route::livewire('certifications/{certification}', 'pages::admin.certification')->name('certifications.show');

    // Signalements (W07.2) : liste (S-61), détail et décision (S-62).
    Route::livewire('reports', 'pages::admin.reports')->name('reports.index');
    Route::livewire('reports/{report}', 'pages::admin.report')->name('reports.show');
});
