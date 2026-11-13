<?php

use App\Exceptions\ApiError;
use App\Http\Middleware\EnsureCliVersion;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\HealthCheck;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // CLI trop ancien : 426 (TRD §17, T-11).
        $middleware->appendToGroup('api', EnsureCliVersion::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->getHost() === config('koryhub.domains.api') || $request->expectsJson(),
        );

        // Sur l'API, toute erreur prend la forme { message, code, errors } (TRD §17).
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->getHost() !== config('koryhub.domains.api') || $exception instanceof ApiError) {
                return null;
            }

            // Une erreur de routage arrive avant le middleware de langue.
            app()->setLocale(SetLocale::resolve($request)->value);

            $error = match (true) {
                $exception instanceof ValidationException => ApiError::make(422, 'validation_failed', $exception->errors()),
                $exception instanceof AuthenticationException => ApiError::make(401, 'token_invalid'),
                $exception instanceof ThrottleRequestsException => ApiError::make(429, 'rate_limited'),
                $exception instanceof NotFoundHttpException, $exception instanceof ModelNotFoundException => ApiError::make(404, 'not_found'),
                $exception instanceof HttpExceptionInterface => ApiError::make($exception->getStatusCode(), 'http_error'),
                default => config('app.debug') ? null : ApiError::make(500, 'server_error'),
            };

            return $error?->render();
        });
    })->create();
