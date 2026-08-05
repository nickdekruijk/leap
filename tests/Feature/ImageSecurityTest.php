<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

/**
 * This route is public, unauthenticated, and takes a file path from the URL.
 * What it will and will not read is therefore worth pinning down.
 */
class ImageSecurityTest extends ImageTestCase
{
    public function test_a_path_cannot_climb_out_of_the_disk(): void
    {
        $this->fakeDisks();

        $this->get('/img/1200/..%2F..%2F.env-deadbeef.webp')->assertNotFound();
        $this->get('/img/1200/'.urlencode('../../.env').'-deadbeef.webp')->assertNotFound();
    }

    public function test_only_bitmaps_are_served(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('config.php', '<?php echo getenv("APP_KEY");');
        Storage::disk('public')->put('logo.svg', '<svg onload="alert(1)"></svg>');

        // Not "it fails to resize" but "it is never read": this route would
        // otherwise be a way to have any file on the disk echoed back.
        $this->get('/img/1200/config.php-deadbeef.webp')->assertNotFound();
        $this->get('/img/1200/logo.svg-deadbeef.webp')->assertNotFound();
        $this->get('/img/1200/config-deadbeef.php.webp')->assertNotFound();
    }

    public function test_an_unknown_preset_is_a_404(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(800, 600));

        // Without the allowlist every made-up number would cost a decode and a
        // file on disk.
        $this->get('/img/9999/pic-deadbeef.jpg.webp')->assertNotFound();
        $this->assertSame([], Storage::disk('leap-images')->allFiles());
    }

    public function test_a_hash_from_another_file_never_serves_this_one(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('a.jpg', $this->jpegBytes(800, 600));
        Storage::disk('public')->put('b.jpg', $this->jpegBytes(400, 300));
        $a = Media::forFile('a.jpg');
        $b = Media::forFile('b.jpg');

        $response = $this->get('/img/1200/b-'.substr($a->sha256, 0, 8).'.jpg.webp');

        $response->assertRedirect($b->url(1200));
    }

    public function test_a_truncated_hash_is_redirected_rather_than_served(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(800, 600));
        $media = Media::forFile('pic.jpg');

        // One picture, one address: a shorter prefix would otherwise fill the
        // disk with copies of the same thing.
        $this->get('/img/1200/pic-'.substr($media->sha256, 0, 6).'.jpg.webp')
            ->assertRedirect($media->url(1200));
    }
}
