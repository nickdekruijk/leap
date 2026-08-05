<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

class ImageRouteTest extends ImageTestCase
{
    private function media(string $path = 'photos/pic.jpg', int $width = 2000, int $height = 1000): Media
    {
        $this->fakeDisks();
        Storage::disk('public')->put($path, $this->jpegBytes($width, $height));

        return Media::forFile($path);
    }

    public function test_a_resized_copy_is_generated_and_returned(): void
    {
        $media = $this->media();

        $response = $this->get($media->url(1200));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/webp');

        $image = Media::imageManager()->read($response->getContent());
        $this->assertSame(1200, $image->width());
        $this->assertSame(600, $image->height());
    }

    public function test_the_copy_is_written_where_the_web_server_will_find_it(): void
    {
        $media = $this->media();

        $this->get($media->url(1200))->assertOk();

        // From here on this URL is answered off disk without PHP -- which is
        // also why nothing may ever overwrite it in place.
        Storage::disk('leap-images')->assertExists('1200/photos/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
    }

    public function test_a_generated_copy_is_cached_forever(): void
    {
        $media = $this->media();

        $response = $this->get($media->url(1200));

        // Safe because the URL changes whenever the file does.
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=31536000', $response->headers->get('Cache-Control'));
    }

    public function test_the_second_request_is_served_from_the_copy_on_disk(): void
    {
        $media = $this->media();
        $url = $media->url(1200);

        $this->get($url)->assertOk();

        // Nothing left to decode: delete the original and the copy still serves.
        Storage::disk('public')->delete('photos/pic.jpg');

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_an_original_smaller_than_the_preset_is_not_blown_up(): void
    {
        $media = $this->media('small.jpg', 300, 200);

        $response = $this->get($media->url(1200));

        $image = Media::imageManager()->read($response->getContent());
        $this->assertSame(300, $image->width());
    }

    public function test_a_missing_original_is_a_404_that_is_not_cached(): void
    {
        $media = $this->media();
        $url = $media->url(1200);
        Storage::disk('public')->delete('photos/pic.jpg');

        $response = $this->get($url);

        // Unlike a resized copy, this URL becomes valid again the moment the
        // file is put back, so it must not be remembered as missing.
        $response->assertNotFound();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
