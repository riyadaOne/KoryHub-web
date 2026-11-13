<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API : un compte suspendu reçoit 403 account_suspended sans que ses jetons soient révoqués
 * (TRD §7.2) ; un compte supprimé n'a plus de jeton utilisable.
 */
class EnsureApiAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            throw ApiError::make(403, 'account_suspended');
        }

        return $next($request);
    }
}
