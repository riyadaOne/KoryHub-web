<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes ouvertes aux visiteurs sans compte (F-RCH-15) : sans jeton, la requête passe en
 * visiteur ; avec un jeton, il doit être valable (401 token_invalid sinon), et le compte est
 * reconnu (limite par compte, modules privés de l'auteur, lien privé de téléchargement).
 */
class AuthenticateIfToken
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');

        if ($request->bearerToken() !== null && Auth::guard('sanctum')->user() === null) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
