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
 * is declared by hand, and a browser test holds it to the truth (any cookie that turns
 * up without being declared fails the build).
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
     * belongs to. This is what the cookie table renders and what the browser test
     * measures the real world against.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function cookies(): Collection
    {
        return static::categories()->flatMap(
            fn (array $category, string $key): array => collect($category['services'] ?? [])
                ->flatMap(fn (array $service): array => collect($service['cookies'] ?? [])
                    ->map(fn (array $cookie): array => $cookie + [
                        'category' => $key,
                        'service' => $service['name'] ?? '',
                        'provider' => $service['provider'] ?? '',
                    ])
                    ->all())
                ->all()
        )->values();
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
     */
    public static function version(): string
    {
        return substr(md5(json_encode([
            static::categories()->keys()->all(),
            static::cookies()->pluck('name')->sort()->values()->all(),
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
