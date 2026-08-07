<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Leap;

/**
 * A line in the log when a page is asked for that is not there.
 *
 * Off unless config('leap.not_found_log.enabled') says otherwise, because a missing page
 * is not a fault of the application and most 404s are a scanner working through a
 * wordlist. Switched on, it answers two questions: which link is broken and where does it
 * live, and was this a visitor or a machine.
 *
 * The second one is why the anonymized IP and the user agent are on by default — a bare
 * path cannot tell them apart, and the answer decides whether there is anything to fix at
 * all. Anonymized because the question is which network, never which person; the whole
 * address takes deliberately switching that off.
 *
 * Two things it deliberately is not:
 *
 * - It is not a report(). Symfony's HttpException is on Laravel's internal do-not-report
 *   list, so a report callback never sees a 404 at all; taking it off that list would
 *   hand every 403 and every abort() to whatever else is listening, Sentry included. This
 *   hangs off render() instead and returns null, so the error page renders as it would.
 * - It is not one line per request. The same path is written once per throttle window.
 *   Without that the log is unreadable exactly when it matters, and the disk fills at a
 *   rate a stranger chooses.
 */
class NotFoundLog
{
    public static function enabled(): bool
    {
        return (bool) config('leap.not_found_log.enabled', false);
    }

    /**
     * Write the line, unless this path has been written recently.
     */
    public static function record(Request $request): void
    {
        if (! static::enabled()) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');

        // add() is the atomic half of the cache contract: it returns false when the key
        // is already there, so two requests for the same missing path in the same second
        // cannot both decide they are the first.
        $window = (int) config('leap.not_found_log.throttle_minutes', 60);

        if ($window > 0 && ! Cache::add('leap:404:'.sha1($path), true, now()->addMinutes($window))) {
            return;
        }

        $channel = config('leap.not_found_log.channel');

        (
            $channel ? Log::channel($channel) : Log::getFacadeRoot()
        )->log(
            (string) config('leap.not_found_log.level', 'info'),
            '404 '.$path,
            static::context($request)
        );
    }

    /**
     * What is worth knowing about a missing page.
     *
     * The referer is the reason this is useful at all — it names the page carrying the
     * broken link. It is also written by whoever made the request, so it goes in trimmed
     * rather than trusted at whatever length they chose, and as log context rather than
     * inside the message, where the formatter escapes it instead of letting a newline
     * forge a second line.
     *
     * @return array<string, string>
     */
    protected static function context(Request $request): array
    {
        $context = [];

        if (config('leap.not_found_log.referer', true) && $referer = $request->headers->get('referer')) {
            $context['referer'] = Str::limit(static::trimReferer($referer), 200);
        }

        if (config('leap.not_found_log.ip_address')) {
            $context['ip'] = config('leap.not_found_log.ip_address_anonymized', true)
                ? Leap::anonymizeIp($request->ip())
                : $request->ip();
        }

        if (config('leap.not_found_log.user_agent')) {
            $context['user_agent'] = Str::limit((string) $request->userAgent(), 200);
        }

        return array_filter($context, fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * The referer, by default whole.
     *
     * The query string is usually part of the answer rather than noise: "?page=3" says
     * which page of a listing carried the dead link, "?utm_source=..." says the newsletter
     * did. And it is nearly always one of your own URLs — browsers have defaulted to
     * strict-origin-when-cross-origin for years, so a referer from somewhere else arrives
     * as a bare origin with no path and no query at all.
     *
     * Set referer_query_string to false for a site whose own URLs carry something it would
     * rather not have in a log.
     */
    protected static function trimReferer(string $referer): string
    {
        if (config('leap.not_found_log.referer_query_string', true)) {
            return $referer;
        }

        return strtok($referer, '?#') ?: $referer;
    }
}
