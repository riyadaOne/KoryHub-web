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

    // Modules publics (S-65, S-66), comptes (S-67, S-68), avis (S-69) : W07.2 ; jamais un module privé (T-13).
    Route::livewire('modules', 'pages::admin.modules')->name('modules.index');
    Route::livewire('modules/{module}', 'pages::admin.module')->name('modules.show');
    Route::livewire('users', 'pages::admin.users')->name('users.index');
    Route::livewire('users/{user}', 'pages::admin.user')->name('users.show');
    Route::livewire('reviews', 'pages::admin.reviews')->name('reviews.index');

    // Plateforme (W08) : catégories (S-70), journal d'audit en lecture seule (S-71), canaux d'alerte (S-73).
    Route::livewire('categories', 'pages::admin.categories')->name('categories.index');
    Route::livewire('audit', 'pages::admin.audit')->name('audit.index');
    Route::livewire('audit/{event}', 'pages::admin.audit-event')->name('audit.show');
    Route::livewire('settings', 'pages::admin.settings')->name('settings');
});
