<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');

    // Jetons du CLI (S-41, S-42) : comme S-26, accessibles avant la vérification de l'e-mail.
    Route::livewire('settings/tokens', 'pages::settings.tokens')->name('tokens.index');
    Route::livewire('settings/tokens/create', 'pages::settings.token-create')
        ->middleware('password.confirm')
        ->name('tokens.create');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    // Journal de sécurité (S-40, F-AUT-19) : visible par l'intéressé seul.
    Route::livewire('settings/security-log', 'pages::settings.security-log')->name('security-log.index');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
