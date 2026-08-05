<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

class MediaSyncFromDiskTest extends ImageTestCase
{
    public function test_it_rereads_what_the_file_now_is(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.png', $this->pngBytes(120, 80));
        $media = Media::forFile('pic.png');
        $media->dimensions();

        Storage::disk('public')->put('pic.png', $this->pngBytes(60, 40));

        $this->assertTrue($media->syncFromDisk('Replaced'));

        $media->refresh();
        $this->assertSame(hash('sha256', Storage::disk('public')->get('pic.png')), $media->sha256);
        $this->assertSame(Storage::disk('public')->size('pic.png'), $media->size);

        // The cached dimensions have to go with it, or the frontend keeps
        // reserving the shape of the picture that used to be there.
        $this->assertSame(['width' => 60, 'height' => 40], $media->dimensions());
    }

    public function test_it_records_what_happened(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.png', $this->pngBytes(120, 80));
        $media = Media::forFile('pic.png');
        $before = count($media->history);

        Storage::disk('public')->put('pic.png', $this->pngBytes(60, 40));
        $media->syncFromDisk('Cropped from 120x80 to 60x40');

        $history = $media->history;
        $this->assertCount($before + 1, $history);
        $this->assertStringContainsString('Cropped from 120x80 to 60x40', end($history));
    }

    public function test_an_unchanged_file_is_left_alone(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.png', $this->pngBytes(120, 80));
        $media = Media::forFile('pic.png');
        $media->dimensions();
        $history = $media->history;

        $this->assertFalse($media->syncFromDisk('Nothing to see'));
        $this->assertSame($history, $media->fresh()->history);

        // And the dimensions it already knows are not thrown away for nothing.
        $this->assertSame(120, $media->fresh()->meta['width']);
    }

    public function test_a_missing_file_changes_nothing(): void
    {
        $this->fakeDisks();
        Storage::disk('public')->put('pic.png', $this->pngBytes(120, 80));
        $media = Media::forFile('pic.png');
        $sha256 = $media->sha256;

        Storage::disk('public')->delete('pic.png');

        $this->assertFalse($media->syncFromDisk());
        $this->assertSame($sha256, $media->fresh()->sha256);
    }
}
