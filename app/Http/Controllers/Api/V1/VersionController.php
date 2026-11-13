<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Modules\PublicationError;
use App\Services\Modules\VersionPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Publication d'une version par kory push (F-REG-04, TRD §13, §22).
 */
class VersionController extends Controller
{
    /**
     * Publier une version.
     *
     * Archive .tar.gz (10 Mo au plus) et son empreinte SHA-256. Premier envoi du compte :
     * `rights_certified` et `notice_accepted`. Nouveau module : `visibility` (privé par défaut).
     * Refus : `{message, code}` avec, entre autres, `secret_detected`, `version_exists`,
     * `version_not_higher`, `binary_forbidden`, `svg_forbidden`, `terms_required`, `email_unverified`.
     */
    public function store(Request $request, VersionPublisher $publisher, string $author, string $module): JsonResponse
    {
        $validated = $request->validate([
            'archive' => ['required', 'file'],
            'sha256' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'visibility' => ['nullable', 'in:public,private'],
            'rights_certified' => ['nullable', 'boolean'],
            'notice_accepted' => ['nullable', 'boolean'],
        ]);

        try {
            $version = $publisher->publish($request->user(), $author, $module, $request->file('archive'), $validated['sha256'], [
                'visibility' => $validated['visibility'] ?? null,
                'rights_certified' => (bool) ($validated['rights_certified'] ?? false),
                'notice_accepted' => (bool) ($validated['notice_accepted'] ?? false),
            ]);
        } catch (PublicationError $error) {
            throw $error->toApiError();
        }

        return response()->json([
            /** Nom complet @auteur/nom. */
            'name' => $version->module->fullName(),
            /** Numéro publié. */
            'version' => $version->version,
            /** Empreinte SHA-256 de l'archive. */
            'sha256' => $version->archive_sha256,
            /** Nombre de fichiers du kory (hors kory.json et README.md). */
            'files_count' => $version->files_count,
            /** Taille de l'archive en octets. */
            'size' => $version->archive_size,
            /** Adresse de la fiche. */
            'url' => route('home').'/'.ltrim($version->module->fullName(), '/'),
        ], 201);
    }
}
