<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Contracts\Sitemapable;

/**
 * A Sitemapable that returns fixed entries, for testing the Sitemap merger
 * without a database.
 */
class CannedSitemapModel implements Sitemapable
{
    public static function sitemapEntries(): Collection
    {
        return collect([
            ['loc' => 'https://example.test/one', 'lastmod' => null, 'alternates' => []],
            ['loc' => 'https://example.test/two', 'lastmod' => null, 'alternates' => []],
        ]);
    }
}
