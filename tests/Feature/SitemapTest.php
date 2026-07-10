<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Classes\Sitemap;
use NickDeKruijk\Leap\Tests\Fixtures\CannedSitemapModel;
use NickDeKruijk\Leap\Tests\Fixtures\TestModel;
use NickDeKruijk\Leap\Tests\TestCase;

class SitemapTest extends TestCase
{
    public function test_entries_are_empty_when_no_models_are_configured(): void
    {
        config(['leap.sitemap.models' => []]);

        $this->assertTrue(Sitemap::entries()->isEmpty());
    }

    public function test_entries_merge_every_sitemapable_model(): void
    {
        config(['leap.sitemap.models' => [CannedSitemapModel::class, CannedSitemapModel::class]]);

        // Two models, two entries each.
        $this->assertCount(4, Sitemap::entries());
    }

    public function test_entries_skip_missing_and_non_sitemapable_classes(): void
    {
        config(['leap.sitemap.models' => [
            CannedSitemapModel::class,
            'App\\Models\\DoesNotExist',
            TestModel::class, // exists but does not implement Sitemapable
        ]]);

        $entries = Sitemap::entries();

        $this->assertCount(2, $entries);
        $this->assertSame('https://example.test/one', $entries->first()['loc']);
    }
}
