<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Informations pour le CLI (TRD §17) : version de l'API, version minimale et
 * dernière version du CLI. Le CLI signale une nouvelle version sans l'installer (P3).
 */
class MetaController extends Controller
{
    /**
     * Versions de l'API et du CLI.
     *
     * @unauthenticated
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            /** Version de l'API. */
            'api_version' => 'v1',
            /** Version minimale du CLI acceptée (x.y.z). */
            'minimum_cli_version' => (string) config('koryhub.cli.minimum_version'),
            /** Dernière version publiée du CLI (x.y.z). */
            'latest_cli_version' => (string) config('koryhub.cli.latest_version'),
        ]);
    }
}
