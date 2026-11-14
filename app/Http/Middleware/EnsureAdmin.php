<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration (W06.2, F-AUT-22, T-16) : rôle admin obligatoire (403 sinon) et double
 * authentification activée ; un admin sans elle est renvoyé vers ses réglages de sécurité,
 * avec un bandeau (décision W06 4a). Aucun écran de l'administration ne s'affiche avant.
 */
class EnsureAdmin
{
    public const SESSION_FLAG = 'admin_two_factor_required';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            // En session (pas un message flash) : la confirmation du mot de passe s'intercale avant la page.
            $request->session()->put(self::SESSION_FLAG, true);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
