<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Persist the user's chosen UI language (session + long-lived cookie),
     * then return to the previous page.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        /** @var array<string, array<string, string>> $supported */
        $supported = config('locale.supported', []);

        if (! array_key_exists($locale, $supported)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        $cookieRaw  = config('locale.cookie', 'localmind_locale');
        $cookieName = is_string($cookieRaw) ? $cookieRaw : 'localmind_locale';
        $daysRaw    = config('locale.cookie_days', 365);
        $days       = is_numeric($daysRaw) ? (int) $daysRaw : 365;

        return redirect()
            ->back()
            ->withCookie(cookie()->forever($cookieName, $locale))
            ->cookie($cookieName, $locale, $days * 24 * 60);
    }
}
