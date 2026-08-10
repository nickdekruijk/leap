<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Which Intervention driver this package encodes with, and where that answer
 * comes from. Two config keys claim it: Laravel's own images.driver, holding
 * the names "gd" and "imagick", and intervention/image-laravel's image.driver,
 * holding a driver classname.
 */
class ImageDriverTest extends TestCase
{
    public function test_gd_when_nothing_is_configured(): void
    {
        config(['images.driver' => null, 'image.driver' => null]);

        $this->assertSame(GdDriver::class, Media::imageDriver());
    }

    /**
     * A host app on Laravel 13 sets IMAGE_DRIVER=imagick for its own use of the
     * Image facade. This package used to read only intervention/image-laravel's
     * key, so it went on encoding with GD -- a weaker avif encoder, and no webp
     * effort setting, on a site that had asked for Imagick.
     */
    public function test_laravels_own_images_driver_is_honoured(): void
    {
        config(['images.driver' => 'imagick', 'image.driver' => null]);

        $this->assertSame(ImagickDriver::class, Media::imageDriver());

        config(['images.driver' => 'gd']);

        $this->assertSame(GdDriver::class, Media::imageDriver());
    }

    public function test_the_legacy_driver_classname_is_still_read(): void
    {
        config(['images.driver' => null, 'image.driver' => ImagickDriver::class]);

        $this->assertSame(ImagickDriver::class, Media::imageDriver());
    }

    public function test_laravels_key_wins_over_the_legacy_one(): void
    {
        config(['images.driver' => 'gd', 'image.driver' => ImagickDriver::class]);

        $this->assertSame(GdDriver::class, Media::imageDriver());
    }

    /**
     * Laravel also accepts a custom driver name there, which is not an
     * Intervention driver and cannot stand in for one.
     */
    public function test_an_unknown_driver_name_falls_through(): void
    {
        config(['images.driver' => 'vips', 'image.driver' => ImagickDriver::class]);

        $this->assertSame(ImagickDriver::class, Media::imageDriver());

        config(['image.driver' => null]);

        $this->assertSame(GdDriver::class, Media::imageDriver());
    }

    /**
     * Asserted on the native handle rather than on the manager, which does not
     * report the driver it was built with. This is also what applyEffort() looks
     * at before it sets webp:method, so the two agree by construction.
     */
    public function test_the_manager_is_built_with_that_driver(): void
    {
        config(['images.driver' => 'imagick']);

        $this->assertInstanceOf(\Imagick::class, Media::imageManager()->createImage(1, 1)->core()->native());

        config(['images.driver' => 'gd']);

        $this->assertInstanceOf(\GdImage::class, Media::imageManager()->createImage(1, 1)->core()->native());
    }
}
