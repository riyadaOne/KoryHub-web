<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse un CLI trop ancien (TRD §17, T-11) : réponse 426 « upgrade_required ».
 * Le CLI s'annonce par l'en-tête User-Agent « kory/x.y.z (os; arch) » ;
 * les autres clients (navigateur, outils) ne sont pas concernés.
 */
class EnsureCliVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $version = self::cliVersion((string) $request->userAgent());

        if ($version !== null && version_compare($version, config('koryhub.cli.minimum_version'), '<')) {
            throw ApiError::upgradeRequired();
        }

        return $next($request);
    }

    /** Version du CLI lue dans le User-Agent, ou null si la requête ne vient pas du CLI. */
    public static function cliVersion(string $userAgent): ?string
    {
        return preg_match('/^kory\/(\d+\.\d+\.\d+)\b/', $userAgent, $matches) === 1 ? $matches[1] : null;
    }
}
