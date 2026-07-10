<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Per-locale URLs (and, optionally, sitemap entries) for a flat (non-tree)
 * translatable model whose routes are registered with the Route::leapLocalized()
 * macro.
 *
 * The model must use Spatie\Translatable\HasTranslations with a translatable
 * "slug" attribute, and its routes must follow the macro's naming convention:
 * one named route per locale, "<name>.<locale>" (e.g. service.nl / service.en).
 * The macro already bakes the locale prefix into each named route, so the URLs
 * returned here are complete -- no prefix is added a second time.
 *
 * Intended for the models the page tree does not cover (Service, Story, Blog).
 * Gives them the same language switcher / hreflang data the page tree gets from
 * PageController::localeUrls(), without each project re-implementing it. A model
 * that also `implements Sitemapable` gets sitemapEntries() for free from here.
 */
trait HasLocaleRouting
{
    /**
     * The base route name for this model, e.g. "service" for the service.nl /
     * service.en routes. Override per model. Defaults to the singular, snake_cased
     * class basename.
     */
    public function localeRouteName(): string
    {
        return str(class_basename($this))->singular()->snake()->toString();
    }

    /**
     * URLs for this record in each configured locale, keyed by locale code. A
     * locale is omitted when the record has no slug translation for it (so it is
     * not routable there). Empty when the site is monolingual.
     *
     * @param  array<string, mixed>  $extraParams  Extra route parameters merged into every locale URL
     * @return array<string, array{name: string, url: string, active: bool}>
     */
    public function localeUrls(?string $routeName = null, array $extraParams = []): array
    {
        $locales = config('leap.locales');
        if (! $locales) {
            return [];
        }

        $routeName ??= $this->localeRouteName();
        $active = app()->getLocale();
        $urls = [];

        foreach ($locales as $locale => $name) {
            $slug = $this->getTranslation('slug', $locale, false);
            if ($slug === '' || $slug === null) {
                continue;
            }

            $urls[$locale] = [
                'name' => $name,
                'url' => $this->resolveLocaleUrl($routeName, $locale, $slug, $extraParams),
                'active' => $locale === $active,
            ];
        }

        return $urls;
    }

    /**
     * Resolve the (relative) URL for one locale. The named route already carries
     * the locale prefix (Route::prefix in the leapLocalized macro); the prefix is
     * never prepended here or it would double.
     *
     * @param  array<string, mixed>  $extraParams
     */
    protected function resolveLocaleUrl(string $routeName, string $locale, string $slug, array $extraParams = []): string
    {
        return route($routeName.'.'.$locale, array_merge($extraParams, ['slug' => $slug]), false);
    }

    /**
     * The URL for this record in a single locale (default: the active locale),
     * or null when the record has no slug translation there.
     *
     * @param  array<string, mixed>  $extraParams  Extra route parameters
     */
    public function localeUrl(?string $locale = null, ?string $routeName = null, array $extraParams = []): ?string
    {
        $locale ??= app()->getLocale();

        return $this->localeUrls($routeName, $extraParams)[$locale]['url'] ?? null;
    }

    /**
     * Default Sitemapable implementation: one entry per active record per routable
     * locale, with hreflang alternates. Satisfies Sitemapable when the model also
     * implements it. Override on the model for anything more specific.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    public static function sitemapEntries(): Collection
    {
        return static::sitemapQuery()->get()
            ->flatMap(fn ($model): Collection => $model->localeSitemapEntries())
            ->values();
    }

    /**
     * The query of records to include in the sitemap. Uses the model's active()
     * scope when present, otherwise every record. Override to narrow it.
     */
    protected static function sitemapQuery(): Builder
    {
        $query = static::query();

        return method_exists($query->getModel(), 'scopeActive') ? $query->active() : $query;
    }

    /**
     * Sitemap entries for this single record. Multilingual: one entry per routable
     * locale sharing the same hreflang alternates. Monolingual: a single entry via
     * the active-locale route, no alternates. Empty when the record is not routable.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    public function localeSitemapEntries(): Collection
    {
        $lastmod = isset($this->updated_at) ? $this->updated_at?->toAtomString() : null;
        $urls = $this->localeUrls();

        // Monolingual: no per-locale URLs; emit a single entry for the active locale.
        if (empty($urls)) {
            $locale = app()->getLocale();
            $slug = $this->getTranslation('slug', $locale, false) ?: $this->slug;
            if ($slug === '' || $slug === null) {
                return collect();
            }

            return collect([[
                'loc' => url($this->resolveLocaleUrl($this->localeRouteName(), $locale, (string) $slug)),
                'lastmod' => $lastmod,
                'alternates' => [],
            ]]);
        }

        $alternates = collect($urls)->mapWithKeys(fn (array $u, string $locale): array => [$locale => url($u['url'])])->all();

        return collect($urls)->map(fn (array $u): array => [
            'loc' => url($u['url']),
            'lastmod' => $lastmod,
            'alternates' => $alternates,
        ])->values();
    }
}
