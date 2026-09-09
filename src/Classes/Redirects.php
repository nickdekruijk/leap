<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use NickDeKruijk\Leap\Models\Redirect;

/**
 * The old addresses of a site, and where they go now.
 *
 * Hung off the same render() hook as NotFoundLog and registered before it, which is
 * the whole design. A request that ends in a redirect never touched the database on
 * the way in: the lookup runs only once a 404 has already been thrown, so a site that
 * is not broken pays nothing at all for having this. Middleware would have had to ask
 * on every request instead, and would then have needed a cache to make that bearable.
 *
 * Being first also means a path with a rule is not written down as a broken link.
 * Laravel walks the render callbacks in the order they were registered and stops at
 * the first one that returns a response, so NotFoundLog is never reached for it. What
 * redirects is not a 404.
 *
 * A rule is a path and a destination, and matching is deliberately dull: an exact
 * lookup on a unique index, then the wildcard rules with the longest prefix first.
 * The exact lookup is an index hit whether the table holds ten rules or ten thousand,
 * so there is nothing to cache; the wildcards cannot be found by equality and are
 * cached as a set, which is invalidated whenever a rule is saved.
 */
class Redirects
{
    /**
     * Where the wildcard rules are kept between requests.
     */
    public const WILDCARD_CACHE_KEY = 'leap:redirects:wildcards';

    public static function enabled(): bool
    {
        return (bool) config('leap.redirects.enabled', true);
    }

    /**
     * The response for an address that has somewhere to go, or null to let the 404
     * carry on being a 404.
     */
    public static function resolve(Request $request): ?RedirectResponse
    {
        if (! static::enabled() || ! $request->isMethodSafe()) {
            return null;
        }

        $redirect = static::match(Redirect::normalizePath($request->path()));

        if (! $redirect) {
            return null;
        }

        static::countHit($redirect);

        $destination = $redirect->destination;

        // Whatever brought them here stays attached, so a campaign parameter or a
        // page number survives the move instead of being dropped on the doorstep.
        if ($query = $request->getQueryString()) {
            $destination .= (str_contains($destination, '?') ? '&' : '?').$query;
        }

        return new RedirectResponse($destination, $redirect->status ?: 301);
    }

    /**
     * Write down an address that has nowhere to go.
     *
     * @deprecated 1.16 The worklist has a table of its own; use NotFounds::record().
     *             Kept because 1.15 shipped this as public API, removed in 2.0.
     */
    public static function capture(Request $request): void
    {
        NotFounds::record($request);
    }

    /**
     * @deprecated 1.16 Use NotFounds::enabled(). Removed in 2.0.
     */
    public static function captureEnabled(): bool
    {
        return NotFounds::enabled();
    }

    /**
     * Forget the cached wildcard rules. Called whenever one is written.
     */
    public static function forgetWildcards(): void
    {
        Cache::forget(static::WILDCARD_CACHE_KEY);
    }

    /**
     * Exact first, then the wildcards with the longest prefix, so a rule for
     * oud/diep/* wins over one for oud/* on a path below both.
     */
    protected static function match(string $path): ?Redirect
    {
        if ($path === '') {
            return null;
        }

        $exact = Redirect::query()->usable()->where('path', $path)->first();

        if ($exact) {
            return $exact;
        }

        foreach (static::wildcards() as $redirect) {
            $prefix = substr($redirect->path, 0, -2);

            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $redirect;
            }
        }

        return null;
    }

    /**
     * The wildcard rules, longest prefix first.
     *
     * Cached because these are the ones an equality lookup cannot find, and there are
     * never many: a wildcard stands for a whole section of a previous site.
     *
     * @return Collection<int, Redirect>
     */
    protected static function wildcards()
    {
        $ttl = (int) config('leap.redirects.cache_minutes', 1440);

        $load = fn () => Redirect::query()
            ->usable()
            ->where('wildcard', true)
            ->get()
            ->sortByDesc(fn (Redirect $redirect): int => strlen($redirect->path))
            ->values();

        return $ttl > 0
            ? Cache::remember(static::WILDCARD_CACHE_KEY, now()->addMinutes($ttl), $load)
            : $load();
    }

    /**
     * How often a rule was used and when it last was, which is what says whether an
     * old address still has anyone on it or the rule can go.
     *
     * One extra write per redirect. Volume equals the number of people following an
     * old link, which is small by definition and shrinks over time; switch
     * count_hits off for a site where it is not.
     */
    protected static function countHit(Redirect $redirect): void
    {
        if (! config('leap.redirects.count_hits', true)) {
            return;
        }

        Redirect::query()->whereKey($redirect->getKey())->update([
            'hits' => $redirect->getRawOriginal('hits') + 1,
            'last_used_at' => now(),
        ]);
    }
}
