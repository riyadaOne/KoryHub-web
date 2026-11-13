<?php

use App\Http\Controllers\Api\V1\MetaController;
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
