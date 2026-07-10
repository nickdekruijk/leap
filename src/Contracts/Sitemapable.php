<?php

namespace NickDeKruijk\Leap\Contracts;

use Illuminate\Support\Collection;

/**
 * A model that can contribute entries to the XML sitemap.
 *
 * Register implementing models in config('leap.sitemap.models'); the Sitemap
 * helper merges the entries from each into one <urlset>. Each entry is:
 *
 *     [
 *         'loc'        => 'https://example.test/en/about',   // absolute URL
 *         'lastmod'    => '2026-07-10T12:00:00+00:00'|null,  // Atom string or null
 *         'alternates' => ['nl' => 'https://…', 'en' => 'https://…'], // hreflang map, may be empty
 *     ]
 *
 * A record produces one entry per locale it is routable in; the 'alternates'
 * map is the same for every sibling entry of that record (self-referential
 * hreflang included), matching Google's expectation.
 */
interface Sitemapable
{
    /**
     * All sitemap entries for this model's active records.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    public static function sitemapEntries(): Collection;
}
