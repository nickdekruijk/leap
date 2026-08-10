<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

/**
 * The whole reason the hash is in the URL: an image that gets replaced has to
 * show up replaced, without anyone clearing a cache. Nothing here is about
 * being tidy — a web server that answers a URL off disk never asks again.
 */
class ImageStaleHashTest extends ImageTestCase
{
    public function test_replacing_a_file_changes_every_url_that_points_at_it(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(2000, 1000));
        $media = Media::forFile('pic.jpg');
        $before = $media->url(1200);

        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(1000, 1000));
        $media->syncFromDisk();

        $this->assertNotSame($before, $media->url(1200));
    }

    public function test_a_url_from_before_the_replacement_redirects_to_the_current_one(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(2000, 1000));
        $media = Media::forFile('pic.jpg');
        $stale = $media->url(1200);

        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(1000, 1000));
        $media->syncFromDisk();

        // A visitor holding a page rendered a moment ago is sent on rather than
        // handed the old picture, and no copy is written under the old hash.
        $this->get($stale)->assertRedirect($media->url(1200));
        $this->assertSame([], Storage::disk('leap-images')->allFiles());
    }

    public function test_a_file_replaced_outside_leap_heals_itself(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(2000, 1000));
        $media = Media::forFile('pic.jpg');
        $stale = $media->url(1200);

        // No syncFromDisk(): a deploy script, an rsync, anything that writes to
        // the disk without going through the file manager.
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(1000, 1000));

        $response = $this->get($stale);

        $media->refresh();
        $this->assertSame(hash('sha256', Storage::disk('public')->get('pic.jpg')), $media->sha256);
        $response->assertRedirect($media->url(1200));

        // And the corrected URL serves the file that is there now.
        $image = Media::imageManager()->decodeBinary($this->get($media->url(1200))->getContent());
        $this->assertSame(1000, $image->width());
    }

    public function test_a_hash_that_belongs_to_no_file_does_not_serve_anything(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(2000, 1000));
        $media = Media::forFile('pic.jpg');

        $response = $this->get('/img/1200/pic-deadbeef.jpg.webp');

        $response->assertRedirect($media->url(1200));
        Storage::disk('leap-images')->assertMissing('1200/pic-deadbeef.jpg.webp');
    }
}
