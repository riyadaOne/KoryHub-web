<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Services\Cli\CliTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Jeton qui fait la requête (kory logout, F-AUT-08).
 */
class TokenController extends Controller
{
    /**
     * Révoquer le jeton de ce terminal.
     */
    public function destroy(Request $request, CliTokenService $tokens): Response
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $tokens->revoke($token, $request->user());

        return response()->noContent();
    }
}
