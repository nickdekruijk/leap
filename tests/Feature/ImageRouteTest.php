<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Classes\ImagePreset;
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

        $image = Media::imageManager()->decodeBinary($response->getContent());
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

        $image = Media::imageManager()->decodeBinary($response->getContent());
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

    /**
     * The route has to accept the dot, or every <source> a <picture> offers is a
     * 404 while ImagePreset::find() happily resolves the name in isolation.
     * Caught on a real site, not here: the unit tests around find() and the
     * component both passed while nothing could actually be fetched.
     */
    public function test_a_format_suffixed_preset_is_reachable_over_http(): void
    {
        config(['leap.images.defaults.format' => ['webp' => []]]);

        $media = $this->media();

        $response = $this->get($media->url('1200.webp'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/webp');
    }

    public function test_the_picture_fallback_is_reachable_over_http(): void
    {
        config(['leap.images.defaults.format' => ['webp' => []]]);

        $media = $this->media();

        $response = $this->get($media->url('1200.'.ImagePreset::FALLBACK));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_with_every_encode_slot_taken_the_original_is_served_uncached(): void
    {
        config(['leap.images.max_concurrent' => 2, 'leap.images.concurrency_wait' => 0]);
        $media = $this->media();
        $held = [Cache::lock('leap.image.slot.0', 60), Cache::lock('leap.image.slot.1', 60)];
        array_map(fn ($lock) => $lock->get(), $held);

        $response = $this->get($media->url(1200));

        // The same picture, at full size, and not remembered: once a slot is
        // free this URL has to reach PHP again to get its copy.
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame(Storage::disk('public')->get('photos/pic.jpg'), $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame([], Storage::disk('leap-images')->allFiles());

        array_map(fn ($lock) => $lock->release(), $held);

        $this->get($media->url(1200))->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_one_free_slot_is_enough_to_encode(): void
    {
        config(['leap.images.max_concurrent' => 2, 'leap.images.concurrency_wait' => 0]);
        $media = $this->media();
        $held = Cache::lock('leap.image.slot.0', 60);
        $held->get();

        $this->get($media->url(1200))->assertOk()->assertHeader('Content-Type', 'image/webp');

        // Released after the encode, not kept until it expires.
        $this->assertTrue(Cache::lock('leap.image.slot.1', 60)->get());
        $held->release();
    }

    public function test_a_published_config_without_the_key_still_gets_the_limit(): void
    {
        $images = config('leap.images');
        unset($images['max_concurrent']);
        config(['leap.images' => $images + ['concurrency_wait' => 0]]);
        $media = $this->media();
        Cache::lock('leap.image.slot.0', 60)->get();
        Cache::lock('leap.image.slot.1', 60)->get();

        $this->get($media->url(1200))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_without_a_limit_the_slots_are_ignored(): void
    {
        config(['leap.images.max_concurrent' => null]);
        $media = $this->media();
        Cache::lock('leap.image.slot.0', 60)->get();

        $this->get($media->url(1200))->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_the_preset_segment_takes_one_dot_and_no_more(): void
    {
        // The route pattern is the first gate: a segment that could carry a
        // second dot is a segment that could carry "..".
        $this->get('/img/6.0.0/photos/pic-a1b2c3d4.jpg.webp')->assertNotFound();
        $this->get('/img/../composer.json')->assertNotFound();
    }
}
