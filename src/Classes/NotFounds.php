<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\NotFound;
use NickDeKruijk\Leap\Models\Redirect;

/**
 * The addresses that were asked for and are not there, written down so the list of
 * what to fix builds itself.
 *
 * Off by default, because on a site with nothing to fix it collects other people's
 * wordlists. Switched on it is the inbox of the redirect map: an address nothing
 * matched becomes a row here, someone says where it should go, and the rule takes over.
 *
 * On the same render hook as Redirects and NotFoundLog, and third of the three: an
 * address with a rule redirects and is never written down, because what redirects is
 * not a 404.
 */
class NotFounds
{
    public static function enabled(): bool
    {
        return Redirects::enabled() && (bool) config('leap.redirects.capture.enabled', false);
    }

    /**
     * Note an address with nowhere to go, unless one of the guards says otherwise.
     */
    public static function record(Request $request): void
    {
        if (! static::enabled() || ! $request->isMethodSafe()) {
            return;
        }

        // Before normalizing, because normalizing is what hides this: a path carrying a
        // control character is a probe, not an address anyone linked to. /a%00b.json
        // sanitizes into a word nobody ever typed, and writing that down helps no one.
        if (preg_match('/[\x00-\x1F\x7F]/u', rawurldecode($request->path()))) {
            return;
        }

        $path = Redirect::normalizePath($request->path());

        if ($path === '' || static::ignored($path)) {
            return;
        }

        // add() is the atomic half of the cache contract: it returns false when the key
        // is already there, so two requests for the same missing path in the same second
        // cannot both decide they are the first.
        $window = (int) config('leap.redirects.capture.throttle_minutes', 60);

        if ($window > 0 && ! Cache::add('leap:not-found:'.sha1($path), true, now()->addMinutes($window))) {
            return;
        }

        if ($existing = NotFound::query()->where('path', $path)->first()) {
            $existing->forceFill([
                'hits' => $existing->getRawOriginal('hits') + 1,
                'last_used_at' => now(),
            ])->saveQuietly();

            static::remember($existing, $request);

            return;
        }

        if (! static::makeRoom()) {
            return;
        }

        $notFound = NotFound::create([
            'path' => $path,
            'hits' => 1,
            'last_used_at' => now(),
        ]);

        static::remember($notFound, $request);
    }

    /**
     * Room for one more, making some if the ceiling is in the way.
     *
     * The ceiling used to simply refuse, and that is the wrong way to fail: the throttle
     * is per path, so a scanner walking a wordlist of ten thousand addresses fills the
     * table in minutes and from then on nothing is written down at all, including the
     * dead links this exists to find.
     *
     * So a full table makes room. First by dropping what has gone unasked for
     * retention_days, then the address with the fewest hits and, between equals, the one
     * longest quiet. That is the right measure: a one-off probe sits at one and goes,
     * while an address Google keeps crawling climbs and stays.
     *
     * Only runs at the ceiling, so the ordinary case costs one count and no writes. A
     * relief valve, not a background task.
     */
    protected static function makeRoom(): bool
    {
        $max = (int) config('leap.redirects.capture.max', 1000);

        if ($max <= 0 || NotFound::query()->count() < $max) {
            return true;
        }

        $days = (int) config('leap.redirects.capture.retention_days', 90);

        if ($days > 0) {
            NotFound::query()->where('last_used_at', '<', now()->subDays($days))->delete();
        }

        while (NotFound::query()->count() >= $max) {
            $spare = NotFound::query()->orderBy('hits')->orderBy('last_used_at')->first();

            if (! $spare) {
                return false;
            }

            $spare->delete();
        }

        return true;
    }

    /**
     * Note who asked, in the three ways that answer a question.
     *
     * The pages that carried the dead link say where to go and fix it, rather than only
     * papering over it with a redirect. The user agent and the address say whether this
     * is a visitor following a stale link or a crawler working through a wordlist, which
     * decides whether there is anything to fix at all. The counts separate the newsletter
     * that went to five thousand people from one link on a forum.
     *
     * The last two are off by default, and not by oversight: they describe the visitor
     * rather than the site, and this table is open to everyone with panel access. The
     * address is anonymized unless that is switched off too.
     *
     * One write, whatever is switched on.
     */
    protected static function remember(NotFound $notFound, Request $request): void
    {
        $changes = [];

        if (config('leap.redirects.capture.referer', true) && $referer = $request->headers->get('referer')) {
            $changes['referers'] = static::tally($notFound->referers, $referer);
        }

        if (config('leap.redirects.capture.user_agent', false) && $agent = $request->userAgent()) {
            $changes['user_agents'] = static::tally($notFound->user_agents, $agent);
        }

        if (config('leap.redirects.capture.ip_address', false) && $ip = $request->ip()) {
            $changes['ip_addresses'] = static::tally($notFound->ip_addresses, config('leap.redirects.capture.ip_address_anonymized', true)
                ? Leap::anonymizeIp($ip)
                : $ip);
        }

        if ($changes) {
            $notFound->forceFill($changes)->saveQuietly();
        }
    }

    /**
     * Add one to a value's count, keeping the set to a workable size.
     *
     * At most values_max distinct entries, the ones seen most often, each trimmed to 200
     * characters, which with the ceiling on rows is what bounds this. The counts
     * themselves are not capped, and values_max of 0 lifts the cap on the set as well.
     *
     * There is a cap by default because this is written from an address anyone can ask
     * for. Uncapped, one visitor sending a different value each time grows the column for
     * as long as they care to, and it is read and rewritten on every record.
     *
     * The last slot always goes to the value from this request, whatever its count.
     * Keeping purely the top N looks right and is not: once the slots are full a new
     * value arrives on 1, is dropped in the same breath, and can never climb, so the link
     * that broke this week is the one that never appears.
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
     * The panel's own prefix is always skipped, and not as a nicety: a module denies a
     * role without read permission with a 404 rather than a 403, precisely so the
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

    /**
     * @return Builder<NotFound>
     */
    public static function query(): Builder
    {
        return NotFound::query();
    }
}
