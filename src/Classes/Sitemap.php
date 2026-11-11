<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Contracts\Sitemapable;

/**
 * Merges the sitemap entries of every model listed in config('leap.sitemap.models')
 * into a single collection for the sitemap view.
 *
 * Only classes that exist and implement Sitemapable contribute; anything else in
 * the config is skipped silently, so a stale/removed model reference cannot break
 * the sitemap. The frontend template renders the result with resources/views/sitemap.blade.php.
 */
class Sitemap
{
    /**
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    public static function entries(): Collection
    {
        return collect(config('leap.sitemap.models', []))
            ->filter(fn ($model): bool => is_string($model) && class_exists($model) && is_subclass_of($model, Sitemapable::class))
            ->flatMap(fn (string $model): Collection => $model::sitemapEntries())
            ->values();
    }

    /**
     * One sitemap entry, in the shape Sitemapable::sitemapEntries() returns.
     *
     * Building entries through this keeps their type exact for static analysis:
     * Larastan reads the values of a Collection invariantly, so a literal
     * ['lastmod' => null, 'alternates' => []] does not pass for ?string and
     * array<string, string>, while the return type of this method does.
     *
     * @param  array<string, string>  $alternates  hreflang map of locale to absolute URL
     * @return array{loc: string, lastmod: ?string, alternates: array<string, string>}
     */
    public static function entry(string $loc, ?string $lastmod = null, array $alternates = []): array
    {
        return ['loc' => $loc, 'lastmod' => $lastmod, 'alternates' => $alternates];
    }
}
