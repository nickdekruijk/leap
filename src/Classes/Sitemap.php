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
}
