<?php

namespace NickDeKruijk\Leap\Tests;

use Illuminate\Support\Facades\Storage;

/**
 * Shared setup for the leap.images tests: the feature turned on (it ships off),
 * both disks faked, and helpers that produce real image bytes so the tests
 * exercise the actual encoders rather than a stand-in.
 */
abstract class ImageTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Set here rather than in a test body: the route is only registered
        // when this is true, and that happens while the provider boots.
        $app['config']->set('leap.images.enabled', true);
    }

    /**
     * The faked images disk keeps its url, which Storage::fake() would
     * otherwise drop — every URL this feature builds comes from it.
     */
    protected function fakeDisks(): void
    {
        Storage::fake('public');
        Storage::fake('leap-images', ['url' => '/img']);
    }

    protected function pngBytes(int $width, int $height): string
    {
        $gd = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }

    protected function jpegBytes(int $width, int $height): string
    {
        $gd = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }

    protected function gifBytes(int $width, int $height): string
    {
        $gd = imagecreatetruecolor($width, $height);
        ob_start();
        imagegif($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }

    /**
     * A GIF with two frames, built by hand: GD writes one frame at a time and
     * has no way to produce an animation, but the only thing that matters here
     * is that a second Graphic Control Extension block is present.
     */
    protected function animatedGifBytes(int $width, int $height): string
    {
        $frame = $this->gifBytes($width, $height);
        $control = "\x00\x21\xF9\x04\x00\x00\x00\x00\x00";

        // Once before the image data that is already there, once more so the
        // file claims two frames.
        return substr($frame, 0, 13).$control.$control.substr($frame, 13);
    }

    /**
     * A JPEG carrying an EXIF orientation tag, which GD cannot write. The APP1
     * segment is assembled by hand: "Exif\0\0", a big-endian TIFF header, and a
     * single IFD entry holding tag 0x0112 (Orientation).
     */
    protected function jpegBytesWithOrientation(int $width, int $height, int $orientation): string
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"           // byte order, magic, offset of the first IFD
            ."\x00\x01"                                 // one entry
            ."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00"
            ."\x00\x00\x00\x00";                        // no next IFD

        $payload = "Exif\x00\x00".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        $jpeg = $this->jpegBytes($width, $height);

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
