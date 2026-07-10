<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\TestCase;

class MediaDimensionsTest extends TestCase
{
    private function pngBytes(int $width, int $height): string
    {
        $gd = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }

    public function test_dimensions_are_computed_and_cached_for_a_bitmap(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pic.png', $this->pngBytes(120, 80));

        $media = Media::forFile('pic.png');
        $this->assertInstanceOf(Media::class, $media);

        $this->assertSame(['width' => 120, 'height' => 80], $media->dimensions());

        // Cached in meta so a fresh instance never decodes the file again.
        $fresh = $media->fresh();
        $this->assertSame(120, $fresh->meta['width']);
        $this->assertSame(80, $fresh->meta['height']);
    }

    public function test_dimensions_are_null_for_a_non_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('doc.txt', 'hello');

        $this->assertNull(Media::forFile('doc.txt')->dimensions());
    }
}
