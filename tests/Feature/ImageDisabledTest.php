<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The feature ships off, and off has to mean off: a site running
 * nickdekruijk/imageresize registers a route of its own with a catch-all path
 * segment, and two packages answering overlapping addresses is decided by
 * whichever provider happened to register first.
 */
class ImageDisabledTest extends TestCase
{
    public function test_no_route_is_registered(): void
    {
        $this->assertFalse(config('leap.images.enabled'));
        $this->assertFalse(Route::has('leap.image'));
    }

    public function test_no_disk_is_defined(): void
    {
        $this->assertNull(config('filesystems.disks.leap-images'));
    }

    public function test_media_urls_point_at_the_file_itself(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pic.jpg', 'not really a jpeg');
        $media = Media::forFile('pic.jpg');

        $this->assertStringNotContainsString('/img/', $media->url(1200));
        $this->assertSame('', $media->srcset([600, 1200]));
    }
}
