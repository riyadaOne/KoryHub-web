<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Choisit la langue de la requête (TRD §20) : choix du compte (ajouté en W01),
 * puis cookie « locale », puis en-tête Accept-Language, puis anglais.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(self::resolve($request)->value);

        return $next($request);
    }

    /** Langue retenue pour une requête (utilisée aussi pour traduire les erreurs de routage). */
    public static function resolve(Request $request): Locale
    {
        $fromCookie = Locale::tryFrom((string) $request->cookie(self::COOKIE));

        if ($fromCookie !== null) {
            return $fromCookie;
        }

        // Symfony retient la première langue supportée de l'en-tête (fr-FR → fr) ;
        // sans correspondance, il renvoie la première de la liste, l'anglais.
        return Locale::tryFrom((string) $request->getPreferredLanguage(Locale::values())) ?? Locale::default();
    }
}
