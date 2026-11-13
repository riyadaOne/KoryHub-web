<?php

use Illuminate\Support\Facades\Route;

/*
 * Administration : admin.koryhub.dev (TRD §6). Enregistrée avant le site principal
 * et Fortify (piège 2). Rôle admin et double authentification obligatoire ajoutés en W06.
 */

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/', 'admin.home')->name('home');
});
