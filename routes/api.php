<?php

use Illuminate\Support\Facades\Route;

/*
 * API du CLI : api.koryhub.dev/v1 (TRD §17). Enregistrée dans bootstrap/app.php,
 * sur le domaine de l'API, avec le préfixe v1 et le groupe de middlewares « api ».
 */

Route::get('/', fn () => response()->json([
    'name' => config('app.name').' API',
    'version' => 'v1',
]))->name('index');
