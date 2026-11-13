<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\User;
use App\Services\Modules\DownloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Téléchargement d'une version par kory pull (W04.4, F-RCH-14, F-RCH-15, F-INT-08).
 */
class DownloadController extends Controller
{
    /**
     * Télécharger une version.
     *
     * Redirige (302) vers un lien signé valable 5 minutes. Refus : `not_found` (404),
     * `version_removed` (410), `download_limit_guest` ou `download_limit_user` (429, avec
     * `retry_at` en UTC et l'en-tête `Retry-After`).
     */
    public function __invoke(Request $request, DownloadService $downloads, string $author, string $module, string $version): RedirectResponse
    {
        $found = Module::query()
            ->whereHas('author', fn ($q) => $q->where('handle', $author))
            ->where('name', $module)
            ->first();

        /** @var User|null $user */
        $user = $request->user();
        abort_if($found === null || ! $found->isVisibleTo($user), 404);

        $target = $found->versions()->where('version', $version)->orderByRaw("status = 'rejected'")->latest('submitted_at')->first();
        abort_if($target === null, 404);

        return redirect()->away($downloads->download($target, $user, (string) $request->ip()));
    }
}
