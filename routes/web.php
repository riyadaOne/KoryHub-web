<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');

Route::post('locale/{locale}', LocaleController::class)->name('locale.update');

// Tableau de bord : accessible avant la vérification de l'e-mail, qui ne bloque que publier et signaler (F-AUT-23).
Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Autoriser un terminal (S-26) : page ouverte par kory login.
    Route::livewire('login/device', 'pages::auth.device')->name('device.show');
});

require __DIR__.'/settings.php';
