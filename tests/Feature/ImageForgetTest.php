<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

/**
 * Nothing ever overwrites a resized copy — that is what makes replacing an image
 * work — so every copy has to be taken away on purpose. Where leap knows exactly
 * which ones went stale, it does not wait for leap:images --prune.
 */
class ImageForgetTest extends ImageTestCase
{
    private function media(string $path = 'pic.jpg'): Media
    {
        Storage::disk('public')->put($path, $this->jpegBytes(2000, 1000));

        return Media::forFile($path);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDisks();
        config(['leap.images.widths' => [600, 1200]]);
    }

    public function test_deleting_an_image_takes_its_copies_with_it(): void
    {
        $media = $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();
        $hash = substr($media->sha256, 0, 8);

        $media->delete();

        Storage::disk('leap-images')->assertMissing('600/pic-'.$hash.'.jpg.webp');
        Storage::disk('leap-images')->assertMissing('1200/pic-'.$hash.'.jpg.webp');
    }

    public function test_replacing_an_image_takes_the_copies_of_the_old_one(): void
    {
        $media = $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();
        $stale = substr($media->sha256, 0, 8);

        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(1000, 500));
        $media->syncFromDisk();

        Storage::disk('leap-images')->assertMissing('600/pic-'.$stale.'.jpg.webp');
    }

    public function test_renaming_an_image_takes_the_copies_under_the_old_name(): void
    {
        $media = $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();
        $hash = substr($media->sha256, 0, 8);

        Storage::disk('public')->move('pic.jpg', 'renamed.jpg');
        $media->update(['file_name' => 'renamed.jpg']);

        // Same contents, so every other file keeps its copies — only the ones
        // under the name that no longer exists go.
        Storage::disk('leap-images')->assertMissing('600/pic-'.$hash.'.jpg.webp');
    }

    public function test_it_leaves_another_file_alone(): void
    {
        $one = $this->media('one.jpg');
        Storage::disk('public')->put('two.jpg', Storage::disk('public')->get('one.jpg'));
        $two = Media::forFile('two.jpg');
        $this->artisan('leap:images --warm')->assertSuccessful();

        $one->delete();

        // Byte for byte the same file, so both copies carry the same hash: going
        // by hash alone would take one something still points at.
        Storage::disk('leap-images')->assertExists('600/two-'.substr($two->sha256, 0, 8).'.jpg.webp');
    }

    public function test_a_save_that_changes_nothing_about_the_file_keeps_the_copies(): void
    {
        $media = $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();

        $media->update(['user_id' => null]);
        $media->meta = ['alt' => 'A photograph'];
        $media->save();

        Storage::disk('leap-images')->assertExists('600/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
    }
}
