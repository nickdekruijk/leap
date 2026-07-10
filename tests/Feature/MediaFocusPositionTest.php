<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\TestCase;

class MediaFocusPositionTest extends TestCase
{
    public function test_null_without_meta(): void
    {
        $media = new Media;

        $this->assertNull($media->focusPosition());
    }

    public function test_returns_css_ready_percentages_when_set(): void
    {
        $media = new Media;
        $media->meta = ['image_focus' => ['x' => 12.5, 'y' => 80]];

        $this->assertSame(['x' => 12.5, 'y' => 80.0], $media->focusPosition());
    }

    public function test_null_when_focus_is_incomplete(): void
    {
        $media = new Media;
        $media->meta = ['image_focus' => ['x' => 50]];

        $this->assertNull($media->focusPosition());
    }

    public function test_null_when_focus_is_not_an_array(): void
    {
        $media = new Media;
        $media->meta = ['image_focus' => 'center'];

        $this->assertNull($media->focusPosition());
    }
}
