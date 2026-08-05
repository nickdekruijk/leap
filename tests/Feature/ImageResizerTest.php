<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Intervention\Image\Drivers\Imagick\Driver;
use NickDeKruijk\Leap\Classes\ImagePreset;
use NickDeKruijk\Leap\Classes\ImageResizer;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

class ImageResizerTest extends ImageTestCase
{
    private function preset(array $options): ImagePreset
    {
        config(['leap.images.presets' => ['test' => $options]]);

        return ImagePreset::find('test');
    }

    public function test_contain_keeps_the_ratio(): void
    {
        $encoded = ImageResizer::encode($this->jpegBytes(2000, 1000), $this->preset(['width' => 600]), 'jpg');

        $image = Media::imageManager()->read((string) $encoded);
        $this->assertSame(600, $image->width());
        $this->assertSame(300, $image->height());
    }

    public function test_contain_does_not_upscale_by_default(): void
    {
        $encoded = ImageResizer::encode($this->jpegBytes(300, 200), $this->preset(['width' => 1200]), 'jpg');

        $image = Media::imageManager()->read((string) $encoded);
        $this->assertSame(300, $image->width());
    }

    public function test_cover_crops_to_exactly_the_box(): void
    {
        $encoded = ImageResizer::encode(
            $this->jpegBytes(2000, 1000),
            $this->preset(['width' => 600, 'height' => 600, 'fit' => 'cover']),
            'jpg'
        );

        $image = Media::imageManager()->read((string) $encoded);
        $this->assertSame(600, $image->width());
        $this->assertSame(600, $image->height());
    }

    public function test_the_format_decides_the_encoding(): void
    {
        $webp = ImageResizer::encode($this->jpegBytes(800, 600), $this->preset(['width' => 400, 'format' => 'webp']), 'jpg');
        $this->assertSame('image/webp', $webp->mediaType());

        $kept = ImageResizer::encode($this->jpegBytes(800, 600), $this->preset(['width' => 400, 'format' => null]), 'jpg');
        $this->assertSame('image/jpeg', $kept->mediaType());
    }

    public function test_a_lossless_source_format_ignores_the_quality(): void
    {
        $png = $this->pngWithText();

        $lossy = ImageResizer::encode($png, $this->preset(['width' => 400, 'quality' => 40, 'lossless_from' => []]), 'png');
        $lossless = ImageResizer::encode($png, $this->preset(['width' => 400, 'quality' => 40, 'lossless_from' => ['png']]), 'png');
        $full = ImageResizer::encode($png, $this->preset(['width' => 400, 'quality' => 100, 'lossless_from' => []]), 'png');

        // Both drivers read quality 100 on webp as "encode losslessly", which is
        // what a screenshot full of text needs and what quality 40 would ruin.
        $this->assertNotSame((string) $lossy, (string) $lossless);
        $this->assertSame((string) $full, (string) $lossless);

        // And a jpeg, which is not in lossless_from, is left lossy.
        $jpeg = $this->preset(['width' => 400, 'quality' => 40, 'lossless_from' => ['png']]);
        $this->assertFalse($jpeg->isLossless('jpg'));
    }

    public function test_quality_reaches_the_encoder(): void
    {
        $source = $this->pngWithText();

        $low = ImageResizer::encode($source, $this->preset(['width' => 400, 'quality' => 10, 'lossless_from' => []]), 'png');
        $high = ImageResizer::encode($source, $this->preset(['width' => 400, 'quality' => 95, 'lossless_from' => []]), 'png');

        $this->assertGreaterThan(strlen((string) $low), strlen((string) $high));
    }

    public function test_effort_buys_a_smaller_file_at_the_same_quality(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('libwebp\'s method is only reachable through the Imagick driver.');
        }

        config(['image.driver' => Driver::class]);
        $source = $this->pngWithText();

        $lazy = ImageResizer::encode($source, $this->preset(['width' => 400, 'quality' => 80, 'effort' => 0, 'lossless_from' => []]), 'png');
        $hard = ImageResizer::encode($source, $this->preset(['width' => 400, 'quality' => 80, 'effort' => 6, 'lossless_from' => []]), 'png');

        // Same picture, same quality target, fewer bytes for more work — paid
        // once, on a file the web server then serves forever.
        $this->assertLessThan(strlen((string) $lazy), strlen((string) $hard));
    }

    public function test_effort_is_ignored_where_there_is_no_encoder_for_it(): void
    {
        // GD has no equivalent, and its native handle would not know what to do
        // with the option; asking for it must not be an error.
        config(['image.driver' => \Intervention\Image\Drivers\Gd\Driver::class]);

        $encoded = ImageResizer::encode($this->jpegBytes(800, 600), $this->preset(['width' => 400, 'effort' => 6]), 'jpg');

        $this->assertNotNull($encoded);
        $this->assertSame('image/webp', $encoded->mediaType());
    }

    public function test_an_animated_gif_is_left_alone(): void
    {
        $preset = $this->preset(['width' => 100]);

        // GD writes one frame at a time, so a resized copy would quietly stop
        // moving. Better a large animation than a still.
        $this->assertNull(ImageResizer::encode($this->animatedGifBytes(200, 200), $preset, 'gif'));
        $this->assertNotNull(ImageResizer::encode($this->gifBytes(200, 200), $preset, 'gif'));
    }

    public function test_an_original_beyond_the_pixel_limit_is_left_alone(): void
    {
        config(['leap.images.max_source_pixels' => 10_000]);

        // 200x200 = 40.000 pixels. GD would want roughly four bytes each, and a
        // real 48 megapixel photo is what this guard is actually about.
        $this->assertNull(ImageResizer::encode($this->jpegBytes(200, 200), $this->preset(['width' => 100]), 'jpg'));
    }

    public function test_unreadable_bytes_do_not_throw(): void
    {
        $this->assertNull(ImageResizer::encode('this is not an image', $this->preset(['width' => 100]), 'jpg'));
    }

    public function test_the_target_path_mirrors_the_source_and_carries_the_hash(): void
    {
        $webp = $this->preset(['width' => 1200, 'format' => 'webp']);

        $this->assertSame(
            'test/photos/office-a1b2c3d4.jpg.webp',
            ImageResizer::targetPath('photos/office.jpg', $webp, 'a1b2c3d4')
        );

        // A file at the root of the disk keeps its place there.
        $this->assertSame('test/office-a1b2c3d4.jpg.webp', ImageResizer::targetPath('office.jpg', $webp, 'a1b2c3d4'));

        $this->assertSame(
            'test/photos/office-a1b2c3d4.jpg',
            ImageResizer::targetPath('photos/office.jpg', $this->preset(['width' => 1200, 'format' => null]), 'a1b2c3d4')
        );
    }

    public function test_a_target_path_reads_back_as_the_original_it_was_made_from(): void
    {
        $webp = $this->preset(['width' => 1200, 'format' => 'webp']);

        $this->assertSame(
            ['path' => 'photos/office.jpg', 'hash' => 'a1b2c3d4'],
            ImageResizer::parseTargetPath('photos/office-a1b2c3d4.jpg.webp', $webp)
        );

        // Greedy: an original that has a hash-shaped tail of its own keeps it.
        $this->assertSame(
            ['path' => 'photos/office-deadbeef.jpg', 'hash' => 'a1b2c3d4'],
            ImageResizer::parseTargetPath('photos/office-deadbeef-a1b2c3d4.jpg.webp', $webp)
        );

        // Nothing this package would ever have written.
        $this->assertNull(ImageResizer::parseTargetPath('photos/office.jpg.webp', $webp));
    }

    public function test_every_path_survives_the_round_trip(): void
    {
        $preset = $this->preset(['width' => 1200, 'format' => 'webp']);

        foreach (['office.jpg', 'photos/office.jpg', 'a/b/c/my photo-2.jpeg', 'photos/already.webp'] as $path) {
            $this->assertSame(
                ['path' => $path, 'hash' => 'a1b2c3d4'],
                ImageResizer::parseTargetPath(
                    substr(ImageResizer::targetPath($path, $preset, 'a1b2c3d4'), strlen($preset->name) + 1),
                    $preset
                ),
                $path
            );
        }
    }

    /**
     * Noise, so the encoders have something to actually compress — a blank
     * canvas encodes to the same handful of bytes at every quality.
     */
    private function pngWithText(): string
    {
        $gd = imagecreatetruecolor(800, 600);

        for ($x = 0; $x < 800; $x += 2) {
            for ($y = 0; $y < 600; $y += 2) {
                imagesetpixel($gd, $x, $y, imagecolorallocate($gd, ($x * 7) % 255, ($y * 13) % 255, ($x + $y) % 255));
            }
        }

        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }
}
