<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Which Intervention driver this package encodes with, and where that answer comes
 * from. Two config keys claim it: intervention/image-laravel's image.driver, holding a
 * driver classname, and Laravel's own images.default, holding the names "gd" and
 * "imagick" and fed by IMAGE_DRIVER.
 */
class ImageDriverTest extends TestCase
{
    public function test_gd_when_nothing_is_configured(): void
    {
        config(['images.default' => null, 'image.driver' => null]);

        $this->assertSame(GdDriver::class, Media::imageDriver());
    }

    /**
     * A host app on Laravel 13 sets IMAGE_DRIVER=imagick for its own use of the Image
     * facade. This package used to read only intervention/image-laravel's key, so it
     * went on encoding with GD -- a weaker avif encoder, and no webp effort setting, on
     * a site that had asked for Imagick.
     */
    public function test_laravels_own_key_is_honoured(): void
    {
        config(['images.default' => 'imagick', 'image.driver' => null]);

        $this->assertSame(ImagickDriver::class, Media::imageDriver());

        config(['images.default' => 'gd']);

        $this->assertSame(GdDriver::class, Media::imageDriver());
    }

    public function test_the_legacy_driver_classname_is_still_read(): void
    {
        config(['images.default' => null, 'image.driver' => ImagickDriver::class]);

        $this->assertSame(ImagickDriver::class, Media::imageDriver());
    }

    /**
     * The legacy key wins, and this is the case that decides the order.
     *
     * config('images.default') answers 'gd' on an app that never chose one -- the
     * framework's own default -- so reading it first would silently move every existing
     * site with a published config/image.php from Imagick to GD, and with it the avif
     * tier and the webp effort setting. The older file can only have been written on
     * purpose, so it outranks a value that may be nothing but a default.
     */
    public function test_a_deliberate_legacy_choice_outranks_the_frameworks_default(): void
    {
        config(['images.default' => 'gd', 'image.driver' => ImagickDriver::class]);

        $this->assertSame(ImagickDriver::class, Media::imageDriver());
    }

    /**
     * Laravel also accepts a custom driver name there, which is not an Intervention
     * driver and cannot stand in for one.
     */
    public function test_an_unknown_driver_name_falls_back_to_gd(): void
    {
        config(['images.default' => 'vips', 'image.driver' => null]);

        $this->assertSame(GdDriver::class, Media::imageDriver());
    }

    /**
     * Asserted on the native handle rather than on the manager, which does not report
     * the driver it was built with. This is also what applyEffort() looks at before it
     * sets webp:method, so the two agree by construction.
     */
    public function test_the_manager_is_built_with_that_driver(): void
    {
        config(['images.default' => 'imagick', 'image.driver' => null]);

        $this->assertInstanceOf(\Imagick::class, Media::imageManager()->createImage(1, 1)->core()->native());

        config(['images.default' => 'gd']);

        $this->assertInstanceOf(\GdImage::class, Media::imageManager()->createImage(1, 1)->core()->native());
    }
}
