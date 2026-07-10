<?php

namespace NickDeKruijk\Leap\Middleware;

use Closure;
use Illuminate\Http\Request;
use NickDeKruijk\Leap\Leap;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set the application locale for a locale-prefixed frontend route.
 *
 * Applied per request (never at route-registration/boot time, which would be too
 * early for a per-request locale). The Route::leapLocalized() macro attaches this
 * to each locale group it generates, passing the group's locale as the parameter:
 *
 *     ->middleware(SetLeapLocale::class.':en')
 *
 * The locale is only applied when it is one of the configured leap.locales, so a
 * stale/removed locale in a route definition can never force an unknown locale.
 * No-op (leaves the default app locale) when the site is monolingual.
 */
class SetLeapLocale
{
    public function handle(Request $request, Closure $next, ?string $locale = null): Response
    {
        $locales = config('leap.locales');

        if ($locales && $locale !== null && array_key_exists($locale, $locales)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
