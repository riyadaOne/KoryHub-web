<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

/**
 * Choisit la langue de la requête (TRD §20) : choix du compte connecté,
 * puis cookie « locale », puis en-tête Accept-Language, puis anglais.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::resolve($request)->value;
        app()->setLocale($locale);
        // Nombres dans la langue de la page (4,6 en français), chiffres occidentaux en arabe (UXUI §10).
        Number::useLocale($locale === 'ar' ? 'ar@numbers=latn' : $locale);

        return $next($request);
    }

    /** Langue retenue pour une requête (utilisée aussi pour traduire les erreurs de routage). */
    public static function resolve(Request $request): Locale
    {
        // Choix enregistré sur le compte (W01) ; sans session (API), pas de compte connecté ici.
        $fromAccount = $request->hasSession() ? Locale::tryFrom((string) $request->user()?->locale) : null;

        if ($fromAccount !== null) {
            return $fromAccount;
        }

        $fromCookie = Locale::tryFrom((string) $request->cookie(self::COOKIE));

        if ($fromCookie !== null) {
            return $fromCookie;
        }

        // Symfony retient la première langue supportée de l'en-tête (fr-FR → fr) ;
        // sans correspondance, il renvoie la première de la liste, l'anglais.
        return Locale::tryFrom((string) $request->getPreferredLanguage(Locale::values())) ?? Locale::default();
    }
}
