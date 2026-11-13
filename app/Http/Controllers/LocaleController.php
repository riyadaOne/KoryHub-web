<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

/**
 * Change la langue de l'interface (sélecteur du pied de page, UXUI §9).
 * Le cookie vaut pour tous les sous-domaines (SESSION_DOMAIN) et dure un an.
 */
class LocaleController extends Controller
{
    public function __invoke(Locale $locale): RedirectResponse
    {
        Cookie::queue(SetLocale::COOKIE, $locale->value, 60 * 24 * 365);

        return redirect()->back(fallback: route('home'));
    }
}
