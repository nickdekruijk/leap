<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Collection;

/**
 * Cookie consent: which categories a site asks about, and which cookies each one
 * actually sets.
 *
 * The registry lives in config('leap.consent') because *which* services a site uses is
 * per project, while everything that reasons about it is not. It is a manifest, not a
 * preference: a scanner can see that a cookie exists, but never what it is for or how
 * long it is kept — and that is precisely what a privacy statement has to state. So it
 * is declared by hand, and ConsentCookieDeclarationTest holds it to the truth: every
 * cookie the server sets has to be in here. What a script sets after the page loads is
 * beyond a test suite without a browser, and belongs in the browser suite of the site
 * that loads it.
 *
 * Nothing here decides what loads: that happens in the browser, because pages are
 * cached server-side and consent-dependent HTML would serve one visitor's choice to
 * the next.
 */
class Consent
{
    /**
     * Is the visitor asked at all? With this off there is no banner, and every category
     * falls back to defaultState() — a site with no trackers wants "denied", a site that
     * knowingly skips the whole question wants "granted".
     */
    public static function enabled(): bool
    {
        return (bool) config('leap.consent.enabled', false);
    }

    /**
     * What a category is worth when nobody was asked.
     */
    public static function defaultState(): bool
    {
        return config('leap.consent.default', 'denied') === 'granted';
    }

    /**
     * Per-category choice, or one accept/refuse for the lot.
     *
     * All-or-nothing is fine when there is only one optional category — a preferences
     * screen with a single switch is theatre. With several distinct purposes it is not:
     * a visitor is entitled to refuse the marketing and keep the analytics.
     */
    public static function granular(): bool
    {
        return (bool) config('leap.consent.granular', true);
    }

    /**
     * Every category, necessary first.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function categories(): Collection
    {
        return collect(config('leap.consent.categories', []));
    }

    /**
     * The categories a visitor can actually say no to.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function optionalCategories(): Collection
    {
        return static::categories()->reject(fn (array $category): bool => $category['necessary'] ?? false);
    }

    /**
     * Every declared cookie, flattened, each carrying the category and service it
     * belongs to. This is what the cookie table renders and what the cookies a request
     * really sets are measured against.
     *
     * ':session' is filled in here rather than in the config file. The session cookie's
     * name is per project (config/session.php derives it from APP_NAME, and a project
     * may set it outright), and config files cannot read each other: they load
     * alphabetically, so leap.php is parsed before session.php exists. A privacy page
     * that prints a placeholder, or guesses the name and gets it wrong, is worse than
     * useless — a visitor checking their browser finds a cookie no row mentions.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function cookies(): Collection
    {
        return static::categories()->flatMap(
            fn (array $category, string $key): array => collect($category['services'] ?? [])
                ->flatMap(fn (array $service): array => collect($service['cookies'] ?? [])
                    ->map(fn (array $cookie): array => ['name' => static::resolveName($cookie['name'] ?? '')] + $cookie + [
                        'category' => $key,
                        'service' => $service['name'] ?? '',
                        'provider' => $service['provider'] ?? '',
                    ])
                    ->all())
                ->all()
        )->values();
    }

    /**
     * The placeholders a declared cookie name may use, resolved against the app config.
     *
     * '*-session' is what the registry used to say, and a site that published
     * config/leap.php still has it. It means the same thing, so it resolves the same way
     * rather than leaving those sites with a pattern on their privacy page forever.
     */
    protected static function resolveName(string $name): string
    {
        return in_array($name, [':session', '*-session'], true)
            ? (string) config('session.cookie')
            : $name;
    }

    /**
     * The cookie names a category is allowed to set.
     *
     * @return array<int, string>
     */
    public static function cookieNames(string $category): array
    {
        return static::cookies()
            ->where('category', $category)
            ->pluck('name')
            ->all();
    }

    /**
     * A fingerprint of the registry, stored alongside the visitor's choice.
     *
     * Consent covers what was on the table when it was given. Add a service and that
     * consent no longer covers it — so the fingerprint changes, the stored choice stops
     * matching, and the banner asks again. Without this a site could quietly start
     * setting cookies a visitor never agreed to.
     *
     * The session cookie goes in under a fixed token instead of its resolved name. It is
     * the same cookie however the registry spells it, so spelling it differently is not a
     * change a visitor has anything to say about — and the token is the string the
     * registry used to declare ('*-session'), so upgrading to ':session' leaves every
     * fingerprint exactly as it was and nobody is asked twice for nothing.
     */
    public static function version(): string
    {
        return substr(md5(json_encode([
            static::categories()->keys()->all(),
            static::cookies()
                ->pluck('name')
                ->map(fn (string $name): string => $name === (string) config('session.cookie') ? '*-session' : $name)
                ->sort()
                ->values()
                ->all(),
        ])), 0, 8);
    }

    /**
     * Everything the browser needs, in one blob for the banner to read.
     *
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'enabled' => static::enabled(),
            'default' => static::defaultState(),
            'granular' => static::granular(),
            'version' => static::version(),
            'categories' => static::optionalCategories()->keys()->all(),
        ];
    }
}
