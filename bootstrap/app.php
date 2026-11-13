<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\HealthCheck;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: function () {
            // Un fichier de routes par domaine (TRD §6). Administration, API et
            // documentation d'abord, puis le site principal (piège 2).
            Route::domain(config('koryhub.domains.admin'))
                ->middleware('web')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::domain(config('koryhub.domains.api'))
                ->middleware('api')
                ->prefix('v1')
                ->name('api.v1.')
                ->group(base_path('routes/api.php'));

            Route::domain(config('koryhub.domains.docs'))
                ->middleware('web')
                ->name('docs.')
                ->group(base_path('routes/docs.php'));

            Route::domain(config('koryhub.domains.main'))
                ->middleware('web')
                ->group(base_path('routes/web.php'));

            // Supervision (UptimeRobot) : répond sur tous les domaines.
            Route::get('/up', HealthCheck::class);
            PreventRequestsDuringMaintenance::except('/up');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Adresse IP réelle derrière Cloudflare (TRD §22, piège 17).
        // La configuration n'est pas encore chargée ici : la liste est lue dans le fichier.
        $middleware->trustProxies(at: (require __DIR__.'/../config/koryhub.php')['trusted_proxies']);

        // Langue de la requête (TRD §20).
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('api', SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->getHost() === config('koryhub.domains.api') || $request->expectsJson(),
        );
    })->create();
