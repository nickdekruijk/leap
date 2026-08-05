<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Classes\ImageUrl;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

class ImageUrlTest extends ImageTestCase
{
    private function media(string $path = 'photos/pic.jpg'): Media
    {
        $this->fakeDisks();
        Storage::disk('public')->put($path, $this->jpegBytes(2000, 1000));

        return Media::forFile($path);
    }

    public function test_the_url_carries_the_preset_and_the_hash_of_the_file(): void
    {
        $media = $this->media();

        $this->assertSame(
            '/img/1200/photos/pic-'.substr($media->sha256, 0, 8).'.jpg.webp',
            $media->url(1200)
        );
    }

    /**
     * Eloquent resolves a property it has no attribute for by looking for a
     * method of that name and insisting it return a relationship. Reading
     * $media->url was null before these methods existed; without an accessor it
     * would now throw, in every project that ever wrote it.
     */
    public function test_reading_url_and_srcset_as_properties_does_not_throw(): void
    {
        $media = $this->media();

        $this->assertStringContainsString('photos/pic.jpg', $media->url);
        $this->assertStringNotContainsString('/img/', $media->url);
        $this->assertStringContainsString('600w', $media->srcset);

        // And the methods still take their arguments.
        $this->assertStringContainsString('/img/1200/', $media->url(1200));
        $this->assertStringContainsString('900w', $media->srcset([900]));
    }

    public function test_without_a_preset_the_original_is_returned(): void
    {
        $media = $this->media();

        $this->assertStringContainsString('photos/pic.jpg', $media->url());
        $this->assertStringNotContainsString('/img/', $media->url());
    }

    public function test_an_svg_is_never_resized(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $media = Media::forFile('logo.svg');

        // Vector: it scales on its own, and rasterising it is a downgrade.
        $this->assertStringNotContainsString('/img/', $media->url(1200));
        $this->assertSame('', $media->srcset([600, 1200]));
    }

    public function test_an_unknown_preset_falls_back_to_the_original(): void
    {
        $media = $this->media();

        $this->assertStringNotContainsString('/img/', $media->url(1201));
    }

    public function test_nothing_is_resized_while_the_feature_is_off(): void
    {
        $media = $this->media();
        config(['leap.images.enabled' => false]);

        $this->assertStringNotContainsString('/img/', $media->url(1200));
        $this->assertSame('', $media->srcset([600, 1200]));
    }

    public function test_srcset_lists_one_entry_per_width(): void
    {
        $media = $this->media();
        $hash = substr($media->sha256, 0, 8);

        $this->assertSame(
            '/img/600/photos/pic-'.$hash.'.jpg.webp 600w, /img/1200/photos/pic-'.$hash.'.jpg.webp 1200w',
            $media->srcset([600, 1200])
        );
    }

    public function test_srcset_skips_a_width_that_is_not_a_preset(): void
    {
        $media = $this->media();

        $this->assertStringNotContainsString('1201w', $media->srcset([600, 1201]));
    }

    public function test_a_path_without_a_media_row_is_hashed_from_disk(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('video-posters/youtube-abc.jpg', $this->jpegBytes(800, 450));

        $hash = substr(hash('sha256', Storage::disk('public')->get('video-posters/youtube-abc.jpg')), 0, 8);

        $this->assertSame(
            '/img/1200/video-posters/youtube-abc-'.$hash.'.jpg.webp',
            Leap::image('video-posters/youtube-abc.jpg', 1200)
        );
    }

    public function test_a_rewritten_path_gets_a_new_hash(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('poster.jpg', $this->jpegBytes(800, 450));
        $before = Leap::image('poster.jpg', 1200);

        // The cached hash is keyed on size and modification time, so a file
        // written over out of band cannot keep answering from the old entry.
        Storage::disk('public')->put('poster.jpg', $this->jpegBytes(400, 300));
        touch(Storage::disk('public')->path('poster.jpg'), time() + 10);

        $this->assertNotSame($before, Leap::image('poster.jpg', 1200));
    }

    public function test_a_missing_file_has_no_url(): void
    {
        $this->fakeDisks();

        $this->assertNull(ImageUrl::for(null, 1200));
        $this->assertStringNotContainsString('/img/', (string) Leap::image('gone.jpg', 1200));
    }
}
