<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Cli\DeviceAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Connexion du CLI par code (TRD §7.3, F-AUT-06) : kory login demande un code, puis interroge
 * l'API jusqu'à l'autorisation sur le site (S-26).
 */
class DeviceAuthorizationController extends Controller
{
    public function __construct(private DeviceAuthorizationService $devices) {}

    /**
     * Demander un code de connexion.
     *
     * Le code affiché (user_code) est valable 15 minutes ; le CLI interroge ensuite
     * POST /v1/auth/device/token toutes les `interval` secondes.
     *
     * @unauthenticated
     */
    public function store(Request $request): JsonResponse
    {
        [$deviceCode, $row] = $this->devices->start($request->userAgent());
        $uri = route('device.show');

        return response()->json([
            /** Code secret de l'appareil, à renvoyer pour obtenir le jeton. */
            'device_code' => $deviceCode,
            /** Code à afficher (ABCD-EFGH). */
            'user_code' => $row->user_code,
            /** Adresse où saisir le code. */
            'verification_uri' => $uri,
            /** Adresse avec le code déjà rempli. */
            'verification_uri_complete' => $uri.'?code='.$row->user_code,
            /** Durée de validité du code, en secondes. */
            'expires_in' => DeviceAuthorizationService::LIFETIME_MINUTES * 60,
            /** Délai entre deux interrogations, en secondes. */
            'interval' => DeviceAuthorizationService::INTERVAL_SECONDS,
        ]);
    }

    /**
     * Obtenir le jeton.
     *
     * Tant que le code n'est pas autorisé : 400 `authorization_pending` (ou `slow_down` si le CLI
     * interroge trop vite). Refusé : 400 `access_denied`. Expiré, inconnu ou déjà utilisé :
     * 400 `expired_token`. Autorisé : le jeton, remis une seule fois.
     *
     * @unauthenticated
     */
    public function token(Request $request): JsonResponse
    {
        $validated = $request->validate(['device_code' => ['required', 'string', 'max:128']]);

        [$plain, $token] = $this->devices->exchange($validated['device_code']);

        return response()->json([
            /** Jeton à envoyer dans l'en-tête Authorization: Bearer. */
            'token' => $plain,
            /** Nom du jeton, visible dans les réglages du site. */
            'token_name' => $token->name,
            /** Date d'expiration (ISO 8601, UTC), ou null pour un jeton sans expiration. */
            'expires_at' => $token->expires_at?->toIso8601ZuluString(),
            /** Nom @auteur du compte. */
            'handle' => $token->tokenable->handle,
        ]);
    }
}
