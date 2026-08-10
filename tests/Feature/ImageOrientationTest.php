<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

/**
 * A photo off a phone is stored the way the sensor read it, with a tag saying
 * which way is up. Every browser turns it; nothing else may disagree, or the
 * width and height printed on the img reserve a box of the wrong shape and the
 * page jumps when the picture loads.
 */
class ImageOrientationTest extends ImageTestCase
{
    public function test_dimensions_report_the_image_as_it_will_be_shown(): void
    {
        $this->fakeDisks();

        // Orientation 6: stored landscape, displayed as a portrait.
        Storage::disk('public')->put('phone.jpg', $this->jpegBytesWithOrientation(400, 300, 6));

        $this->assertSame(['width' => 300, 'height' => 400], Media::forFile('phone.jpg')->dimensions());
    }

    public function test_a_resized_copy_is_turned_the_same_way(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('phone.jpg', $this->jpegBytesWithOrientation(400, 300, 6));
        $media = Media::forFile('phone.jpg');

        $image = Media::imageManager()->decodeBinary($this->get($media->url(600))->getContent());

        // Not upscaled past its 300px displayed width, and portrait either way.
        $this->assertSame(300, $image->width());
        $this->assertSame(400, $image->height());
    }

    public function test_an_upright_photo_is_left_as_it_is(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('flat.jpg', $this->jpegBytesWithOrientation(400, 300, 1));

        $this->assertSame(['width' => 400, 'height' => 300], Media::forFile('flat.jpg')->dimensions());
    }
}
