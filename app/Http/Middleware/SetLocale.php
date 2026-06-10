<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active UI locale for every request, in priority order:
 *
 *   1. Explicit user choice persisted in the session ("locale").
 *   2. The remembered cookie.
 *   3. Browser Accept-Language auto-detection (if enabled).
 *   4. The application default (config('app.locale')).
 *
 * This is a deliberately lightweight, native-Laravel approach: no extra
 * package and no URL rewriting (no /en/ prefixes), which keeps routing fast
 * and the single-user app simple.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        /** @var array<string, array<string, string>> $supported */
        $supported = config('locale.supported', []);
        $available = array_keys($supported);
        $default   = (string) config('app.locale', 'en');

        // 1. Session.
        $session = $request->session()->get('locale');
        if (is_string($session) && in_array($session, $available, true)) {
            return $session;
        }

        // 2. Cookie.
        $cookieName = (string) config('locale.cookie', 'localmind_locale');
        $cookie     = $request->cookie($cookieName);
        if (is_string($cookie) && in_array($cookie, $available, true)) {
            $request->session()->put('locale', $cookie);

            return $cookie;
        }

        // 3. Browser Accept-Language.
        if ((bool) config('locale.auto_detect', true)) {
            $preferred = $request->getPreferredLanguage($available);
            if (is_string($preferred) && in_array($preferred, $available, true)) {
                return $preferred;
            }
        }

        // 4. Default.
        return in_array($default, $available, true) ? $default : 'en';
    }
}
