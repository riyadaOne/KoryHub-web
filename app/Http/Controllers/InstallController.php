<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Scripts d'installation du CLI (K05, TRD §18.1) : copies de KoryHub-cli/scripts, servies avec la
 * version publiée (koryhub.cli.latest_version) et l'adresse des exécutables (koryhub.cli.download_url).
 * Le script vérifie l'empreinte SHA-256 avant d'installer.
 */
class InstallController extends Controller
{
    public function powershell(): Response
    {
        return $this->script('install.ps1');
    }

    public function shell(): Response
    {
        return $this->script('install.sh');
    }

    private function script(string $name): Response
    {
        // Adresse de l'API du site qui sert le script : le CLI installé la vise sans réglage (démo CADEV).
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $apiUrl = $scheme.'://'.config('koryhub.domains.api');

        $content = str_replace(
            ['__KORY_VERSION__', '__KORY_DOWNLOAD_URL__', '__KORY_API_URL__'],
            [(string) config('koryhub.cli.latest_version'), rtrim((string) config('koryhub.cli.download_url'), '/'), $apiUrl],
            File::get(resource_path('install/'.$name)),
        );

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
