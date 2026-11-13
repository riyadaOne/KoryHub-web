<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\ModuleVersion;
use App\Services\Modules\ModuleFinder;
use App\Services\Modules\VersionFiles;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Fiche publique d'un kory (S-03 README, S-05 version, S-06 fichiers, images du README) ;
 * un module privé répond 404 à tout autre que son auteur.
 */
class ModulePageController extends Controller
{
    public function __construct(private ModuleFinder $finder) {}

    /** S-03 : README de la version affichée et colonne latérale. */
    public function show(Request $request, string $author, string $module): View
    {
        $found = $this->finder->find($author, $module, $request->user());
        $version = $this->finder->current($found);

        return view('modules.show', $this->common($request, $found, $version));
    }

    /** S-05 : détail d'une version. */
    public function version(Request $request, string $author, string $module, string $number): View
    {
        $found = $this->finder->find($author, $module, $request->user());
        $version = $this->finder->version($found, $number, $request->user());

        return view('modules.version', $this->common($request, $found, $version));
    }

    /** S-06 : arborescence et fichier choisi (?path=), dans la version demandée (?v=) ou la version affichée. */
    public function files(Request $request, VersionFiles $files, string $author, string $module): View
    {
        $found = $this->finder->find($author, $module, $request->user());
        $version = $request->filled('v')
            ? $this->finder->version($found, (string) $request->query('v'), $request->user())
            : $this->finder->current($found);

        $list = $version?->files()->orderBy('path')->get() ?? collect();
        $selected = $list->firstWhere('path', (string) $request->query('path'))
            ?? $list->first(fn ($file) => ! in_array($file->path, ['kory.json', 'README.md'], true))
            ?? $list->first();

        return view('modules.files', [
            ...$this->common($request, $found, $version),
            'files' => $list,
            'selected' => $selected,
            'content' => $selected && ! $selected->is_image ? $files->content($version, $selected) : null,
        ]);
    }

    /** Image de la documentation d'une version, servie avec son type et nosniff (TRD §22). */
    public function raw(Request $request, VersionFiles $files, string $author, string $module, string $number, string $path): Response
    {
        $found = $this->finder->find($author, $module, $request->user());
        $version = $this->finder->version($found, $number, $request->user());
        $file = $version->files()->where('path_hash', hash('sha256', $path))->first();

        abort_if($file === null || ! $file->is_image, 404);
        $content = $files->content($version, $file);
        abort_if($content === null, 404);

        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

        return response($content, 200, [
            'Content-Type' => $types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** @return array<string, mixed> */
    private function common(Request $request, Module $module, ?ModuleVersion $version): array
    {
        return [
            'module' => $module,
            'version' => $version,
            'isAuthor' => $this->finder->isAuthor($module, $request->user()),
            'versionsCount' => $this->finder->versions($module, $request->user())->count(),
            'filesCount' => $version?->files_count,
        ];
    }
}
