<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VersionStatus;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\User;
use App\Support\SemVer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Audit de kory.lock (W04.6, F-INT-13) : état de chaque version installée. Rien n'est enregistré.
 */
class AuditController extends Controller
{
    /**
     * Auditer des versions installées.
     *
     * État de chaque version : `up_to_date`, `update_available`, `deprecated`, `at_risk`, `removed`
     * ou `not_found` ; `latest_version` est la dernière version stable ; `message` est l'avertissement
     * de sécurité de l'équipe de modération (`at_risk`) ou le message de dépréciation de l'auteur.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modules' => ['required', 'array', 'max:'.config('koryhub.downloads.audit_max_modules')],
            'modules.*.name' => ['required', 'string', 'regex:#^@[a-z0-9-]+/[a-z0-9-]+$#'],
            'modules.*.version' => ['required', 'string', 'max:64'],
        ]);

        /** @var User|null $user */
        $user = $request->user();

        $results = collect($validated['modules'])->map(function (array $entry) use ($user) {
            [$author, $name] = explode('/', ltrim($entry['name'], '@'), 2);

            $module = Module::query()
                ->whereHas('author', fn (Builder $q) => $q->where('handle', $author))
                ->where('name', $name)
                ->with('latestVersion')
                ->first();

            $installed = $module?->isVisibleTo($user)
                ? $module->versions()->where('version', $entry['version'])
                    ->whereIn('status', [VersionStatus::Published, VersionStatus::Deprecated, VersionStatus::Removed])
                    ->first()
                : null;

            $latest = $installed !== null ? $module->latestVersion?->version : null;

            $state = match (true) {
                $installed === null => 'not_found',
                $installed->status === VersionStatus::Removed => 'removed',
                $installed->at_risk_at !== null => 'at_risk',
                $installed->status === VersionStatus::Deprecated => 'deprecated',
                $latest !== null && $this->newer($latest, $installed->version) => 'update_available',
                default => 'up_to_date',
            };

            return [
                'name' => $entry['name'],
                'version' => $entry['version'],
                /** up_to_date, update_available, deprecated, at_risk, removed ou not_found. */
                'state' => $state,
                /** Dernière version stable, ou null. */
                'latest_version' => $latest,
                /** Avertissement de sécurité (at_risk) ou message de dépréciation de l'auteur, ou null. */
                'message' => $state === 'at_risk' ? $installed->at_risk_message : $installed?->deprecation_message,
            ];
        });

        return response()->json(['results' => $results->values()->all()]);
    }

    private function newer(string $latest, string $installed): bool
    {
        $a = SemVer::parse($latest);
        $b = SemVer::parse($installed);

        return $a !== null && $b !== null && $a->greaterThan($b);
    }
}
