<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte suspendu ou supprimé est déconnecté à sa requête suivante (TRD §6, APPFLOW §3.4).
 * Côté API (jetons du CLI), le refus 403 « account_suspended » arrive en W02.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->status !== AccountStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $user->status === AccountStatus::Suspended ? 'account.suspended_message' : 'account.deleted_message';

            return redirect()->route('login')->with('status', __($message));
        }

        return $next($request);
    }
}
