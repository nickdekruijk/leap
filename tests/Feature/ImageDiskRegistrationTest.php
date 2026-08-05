<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Tests\ImageTestCase;

class ImageDiskRegistrationTest extends ImageTestCase
{
    public function test_leap_defines_the_disk_itself(): void
    {
        // So turning the feature on is one config key, not a disk a project has
        // to add to config/filesystems.php by hand.
        $this->assertSame([
            'driver' => 'local',
            'root' => public_path('img'),
            'url' => '/img',
            'visibility' => 'public',
            'serve' => false,
            'throw' => false,
        ], config('filesystems.disks.leap-images'));
    }
}
