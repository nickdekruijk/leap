<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Leap;
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

    public static function captureEnabled(): bool
    {
        return static::enabled() && (bool) config('leap.redirects.capture.enabled', false);
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
     * Write down an address that was asked for and has nowhere to go, so the list of
     * what to fix builds itself instead of being copied out of a log by hand.
     *
     * Off by default. Switched on it is a table anyone can walk down and finish, and
     * the guards below are what keep it that way rather than a transcript of every
     * wordlist on the internet.
     */
    public static function capture(Request $request): void
    {
        if (! static::captureEnabled() || ! $request->isMethodSafe()) {
            return;
        }

        $path = Redirect::normalizePath($request->path());

        if ($path === '' || static::ignored($path)) {
            return;
        }

        // add() is the atomic half of the cache contract: it returns false when the
        // key is already there, so two requests for the same missing path in the same
        // second cannot both decide they are the first.
        $window = (int) config('leap.redirects.capture.throttle_minutes', 60);

        if ($window > 0 && ! Cache::add('leap:redirect-capture:'.sha1($path), true, now()->addMinutes($window))) {
            return;
        }

        $redirect = Redirect::query()->where('path', $path)->first();

        if ($redirect) {
            // A row that exists but did not match is one somebody switched off, or one
            // still waiting for a destination. Either way this is another visitor who
            // wanted it, which is worth knowing.
            static::countHit($redirect);
            static::remember($redirect, $request);

            return;
        }

        $max = (int) config('leap.redirects.capture.max', 1000);

        if ($max > 0 && Redirect::query()->where('detected', true)->count() >= $max) {
            return;
        }

        $redirect = Redirect::create([
            'path' => $path,
            'destination' => null,
            'active' => false,
            'detected' => true,
            'hits' => 1,
            'last_used_at' => now(),
        ]);

        static::remember($redirect, $request);
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

    /**
     * Note who asked for this address, in the three ways that answer a question.
     *
     * The pages that carried the dead link say where to go and fix it, rather than
     * only papering over it with a redirect. The user agent and the address say
     * whether this is a visitor following a stale link or a crawler working through a
     * wordlist, which decides whether there is anything to fix at all. The counts are
     * what separate the newsletter that went to five thousand people from one link on
     * a forum.
     *
     * The last two are off by default, and not by oversight: they describe the visitor
     * rather than the site, and this table is open to everyone with panel access. The
     * address is anonymized unless that is switched off too.
     *
     * One write, whatever is switched on.
     */
    protected static function remember(Redirect $redirect, Request $request): void
    {
        $changes = [];

        if (config('leap.redirects.capture.referer', true) && $referer = $request->headers->get('referer')) {
            $changes['referers'] = static::tally($redirect->referers, $referer);
        }

        if (config('leap.redirects.capture.user_agent', false) && $agent = $request->userAgent()) {
            $changes['user_agents'] = static::tally($redirect->user_agents, $agent);
        }

        if (config('leap.redirects.capture.ip_address', false) && $ip = $request->ip()) {
            $changes['ip_addresses'] = static::tally($redirect->ip_addresses, config('leap.redirects.capture.ip_address_anonymized', true)
                ? Leap::anonymizeIp($ip)
                : $ip);
        }

        if ($changes) {
            $redirect->forceFill($changes)->saveQuietly();
        }
    }

    /**
     * Add one to a value's count, keeping the set to a workable size.
     *
     * At most `values_max` distinct entries, the ones seen most often, each trimmed to
     * 200 characters, which with the ceiling on captured rows is what bounds this —
     * every one of these is chosen by whoever made the request, so their length is
     * decided at this end rather than that one. The counts themselves are not capped,
     * and `values_max` of 0 lifts the cap on the set as well.
     *
     * There is a cap by default because this is written from an address anyone can
     * ask for. Uncapped, one visitor sending a different value each time grows the
     * column for as long as they care to, and it is read and rewritten on every
     * capture. The throttle slows that to once per path per window, but
     * throttle_minutes of 0 removes even that.
     *
     * The last slot always goes to the value from this request, whatever its count.
     * Keeping purely the top N looks right and is not: once the slots are full a new
     * value arrives on 1, is dropped in the same breath, and can never climb — so the
     * link that broke this week, the one actually worth knowing about, is the one that
     * never appears.
     *
     * @param  array<string, int>|null  $tally
     * @return array<string, int>
     */
    protected static function tally(?array $tally, string $value): array
    {
        $value = Str::limit($value, 200);
        $tally = $tally ?? [];
        $tally[$value] = ($tally[$value] ?? 0) + 1;

        arsort($tally);

        $max = (int) config('leap.redirects.capture.values_max', 100);

        if ($max > 0 && count($tally) > $max) {
            // Held aside first: the slice may well have dropped it, and it is the one
            // entry that has to survive.
            $count = $tally[$value];

            $tally = array_slice($tally, 0, $max - 1, true);
            $tally[$value] = $count;

            arsort($tally);
        }

        return $tally;
    }

    /**
     * The addresses not worth writing down.
     *
     * The panel's own prefix is always skipped, and not as a nicety: a module denies
     * a role without read permission with a 404 rather than a 403, precisely so the
     * module's existence stays hidden. Capturing those would fill the table with the
     * panel's own screens and undo that.
     */
    protected static function ignored(string $path): bool
    {
        $prefix = trim((string) config('leap.route_prefix'), '/');

        if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
            return true;
        }

        foreach ((array) config('leap.redirects.capture.ignore', []) as $pattern) {
            if (Str::is(mb_strtolower((string) $pattern), $path)) {
                return true;
            }
        }

        return false;
    }
}
