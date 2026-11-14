<?php

use App\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;

/*
 * Documentation : docs.koryhub.dev (TRD §6, S-13, W09). Accueil dans la langue choisie sur le site,
 * puis /{langue} et /{langue}/{page} ; pages en Markdown dans resources/docs.
 */

Route::get('/', [DocsController::class, 'home'])->name('home');
Route::get('{locale}', [DocsController::class, 'home'])->whereIn('locale', ['en', 'fr', 'ar', 'pt'])->name('locale');
Route::get('{locale}/{page}', [DocsController::class, 'show'])->whereIn('locale', ['en', 'fr', 'ar', 'pt'])->where('page', '[a-z0-9-]+')->name('page');
