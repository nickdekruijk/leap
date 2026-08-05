<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Jobs\GenerateImageDerivatives;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

class ImageEagerTest extends ImageTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.images.eager', true);
    }

    public function test_storing_an_image_queues_its_copies(): void
    {
        Bus::fake();
        $this->fakeDisks();
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(2000, 1000));

        $media = Media::forFile('pic.jpg');

        Bus::assertDispatched(
            GenerateImageDerivatives::class,
            fn (GenerateImageDerivatives $job) => $job->mediaId === $media->id && $job->sha256 === $media->sha256
        );
    }

    public function test_a_file_that_is_not_a_bitmap_queues_nothing(): void
    {
        Bus::fake();
        $this->fakeDisks();
        Storage::disk('public')->put('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        Media::forFile('logo.svg');

        Bus::assertNotDispatched(GenerateImageDerivatives::class);
    }

    public function test_the_job_writes_every_preset(): void
    {
        $this->fakeDisks();
        config(['leap.images.widths' => [600, 1200]]);
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(2000, 1000));
        $media = Media::forFile('pic.jpg');

        (new GenerateImageDerivatives($media->id, $media->sha256))->handle();

        Storage::disk('leap-images')->assertExists('600/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
        Storage::disk('leap-images')->assertExists('1200/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
    }

    public function test_the_job_declines_when_the_file_changed_again_while_it_waited(): void
    {
        // Faked so the listener's own dispatches stay out of the way and the
        // only thing that runs is the job under test.
        Bus::fake();
        $this->fakeDisks();
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(2000, 1000));
        $media = Media::forFile('pic.jpg');
        $stale = $media->sha256;

        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(1000, 500));
        $media->syncFromDisk();

        (new GenerateImageDerivatives($media->id, $stale))->handle();

        // Anything it wrote would be a copy of a picture no URL points at.
        $this->assertSame([], Storage::disk('leap-images')->allFiles());
    }
}
