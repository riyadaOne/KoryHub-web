<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Change la langue de l'interface (sélecteur du pied de page, réglages, UXUI §9).
 * Le cookie vaut pour tous les sous-domaines (SESSION_DOMAIN) et dure un an ;
 * pour un compte connecté, la langue est aussi enregistrée sur le compte (e-mails).
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, Locale $locale): RedirectResponse
    {
        Cookie::queue(SetLocale::COOKIE, $locale->value, 60 * 24 * 365);

        $request->user()?->update(['locale' => $locale->value]);

        return redirect()->back(fallback: route('home'));
    }
}
