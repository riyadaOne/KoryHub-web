<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compte relié au jeton (kory whoami, kory login --with-token).
 */
class UserController extends Controller
{
    /**
     * Compte et jeton en cours.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        return response()->json([
            /** Nom @auteur. */
            'handle' => $user->handle,
            /** Nom affiché, ou null. */
            'name' => $user->name,
            /** Nom du jeton utilisé. */
            'token_name' => $token->name,
            /** Expiration du jeton (ISO 8601, UTC), ou null pour un jeton sans expiration. */
            'token_expires_at' => $token->expires_at?->toIso8601ZuluString(),
            /** Droits certifiés et message de prudence lus (premier kory push fait). */
            'publication_terms_accepted' => $user->hasAcceptedPublicationTerms(),
        ]);
    }
}
