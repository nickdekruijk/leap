<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\ImageTestCase;

class ImageCommandTest extends ImageTestCase
{
    private function media(string $path = 'pic.jpg'): Media
    {
        Storage::disk('public')->put($path, $this->jpegBytes(2000, 1000));

        return Media::forFile($path);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDisks();
        config(['leap.images.widths' => [600, 1200]]);
    }

    public function test_warm_generates_every_preset(): void
    {
        $media = $this->media();
        $hash = substr($media->sha256, 0, 8);

        $this->artisan('leap:images --warm')->assertSuccessful();

        Storage::disk('leap-images')->assertExists('600/pic-'.$hash.'.jpg.webp');
        Storage::disk('leap-images')->assertExists('1200/pic-'.$hash.'.jpg.webp');
    }

    public function test_warm_can_be_limited_to_one_preset(): void
    {
        $media = $this->media();
        $hash = substr($media->sha256, 0, 8);

        $this->artisan('leap:images --warm --preset=600')->assertSuccessful();

        Storage::disk('leap-images')->assertExists('600/pic-'.$hash.'.jpg.webp');
        Storage::disk('leap-images')->assertMissing('1200/pic-'.$hash.'.jpg.webp');
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $media = $this->media();

        $this->artisan('leap:images --warm --dry-run')->assertSuccessful();

        Storage::disk('leap-images')->assertMissing('600/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
    }

    public function test_prune_removes_copies_of_a_file_that_was_replaced(): void
    {
        $media = $this->media();
        $stale = substr($media->sha256, 0, 8);
        $this->artisan('leap:images --warm')->assertSuccessful();

        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(1000, 500));
        $media->syncFromDisk();
        $this->artisan('leap:images --warm')->assertSuccessful();

        $this->artisan('leap:images --prune')->assertSuccessful();

        // Nothing overwrites: the copies under the old hash are what is left
        // behind once the row is re-read outside leap's sight.
        Storage::disk('leap-images')->assertMissing('600/pic-'.$stale.'.jpg.webp');
        Storage::disk('leap-images')->assertExists('600/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
    }

    public function test_prune_removes_copies_of_a_deleted_file_that_shares_its_content(): void
    {
        // Two files, byte for byte the same, so they carry the same hash:
        // deleting one leaves copies that hash alone would keep alive.
        $one = $this->media('one.jpg');
        Storage::disk('public')->put('two.jpg', Storage::disk('public')->get('one.jpg'));
        Media::forFile('two.jpg');
        $this->artisan('leap:images --warm')->assertSuccessful();

        Storage::disk('public')->delete('one.jpg');
        $one->delete();

        $this->artisan('leap:images --prune')->assertSuccessful();

        $hash = substr($one->sha256, 0, 8);
        Storage::disk('leap-images')->assertMissing('600/one-'.$hash.'.jpg.webp');
        Storage::disk('leap-images')->assertExists('600/two-'.$hash.'.jpg.webp');
    }

    public function test_prune_removes_a_preset_that_is_no_longer_configured(): void
    {
        $media = $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();

        config(['leap.images.widths' => [1200]]);
        $this->artisan('leap:images --prune')->assertSuccessful();

        Storage::disk('leap-images')->assertMissing('600/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
        Storage::disk('leap-images')->assertExists('1200/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
    }

    /**
     * The disagreement that costs files: the site encodes AVIF perfectly well over
     * HTTP while the command line cannot, because it runs a PHP without the driver's
     * extension. format() then drops every format it cannot write and answers "the
     * source's own", the copies on disk stop matching what the preset says it writes,
     * and prune reads every one of them as an older layout. Nothing here is broken
     * except what the command was told, so it has to refuse rather than tidy up.
     */
    public function test_it_refuses_to_prune_when_the_driver_can_write_none_of_the_offered_formats(): void
    {
        $media = $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();

        // No driver encodes these, which is the point: it stands in for a build
        // without the AVIF and webp encoders rather than for a typo in the config.
        config(['leap.images.defaults.format' => ['zzz' => [], 'qqq' => []]]);

        $this->artisan('leap:images --prune')
            ->expectsOutputToContain('zzz')
            ->assertFailed();

        Storage::disk('leap-images')->assertExists('600/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
    }

    public function test_it_refuses_to_warm_on_the_same_grounds(): void
    {
        config(['leap.images.defaults.format' => ['zzz' => [], 'qqq' => []]]);

        $this->artisan('leap:images --warm')->assertFailed();

        $this->assertSame([], Storage::disk('leap-images')->allFiles());
    }

    /**
     * One encodable format is enough. The presets still describe the paths on disk,
     * and a format the driver happens not to have is a <source> the markup leaves
     * out rather than a copy that has gone missing.
     */
    public function test_it_prunes_as_usual_when_one_offered_format_can_be_written(): void
    {
        $media = $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();

        config(['leap.images.defaults.format' => ['zzz' => [], 'webp' => []]]);
        config(['leap.images.widths' => [1200]]);

        $this->artisan('leap:images --prune')->assertSuccessful();

        Storage::disk('leap-images')->assertMissing('600/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        $media = $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();
        config(['leap.images.widths' => [1200]]);

        $this->artisan('leap:images --prune --dry-run')->assertSuccessful();

        Storage::disk('leap-images')->assertExists('600/pic-'.substr($media->sha256, 0, 8).'.jpg.webp');
    }

    public function test_clear_empties_the_disk(): void
    {
        $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();

        $this->artisan('leap:images --clear')->assertSuccessful();

        $this->assertSame([], Storage::disk('leap-images')->allFiles());
    }

    public function test_sync_picks_up_a_file_written_outside_leap(): void
    {
        $media = $this->media();
        $stale = $media->url(600);

        // No syncFromDisk(): an rsync, a deploy script, a database import.
        Storage::disk('public')->put('pic.jpg', $this->jpegBytes(1000, 500));

        $this->artisan('leap:images --sync')->assertSuccessful();

        // Without this the pages go on printing the old address, and the web
        // server goes on answering it off disk without ever asking PHP.
        $this->assertNotSame($stale, $media->fresh()->url(600));
    }

    public function test_sync_leaves_an_untouched_file_alone(): void
    {
        $media = $this->media();
        $url = $media->url(600);

        $this->artisan('leap:images --sync')->assertSuccessful();

        $this->assertSame($url, $media->fresh()->url(600));
    }

    public function test_without_options_it_explains_itself(): void
    {
        $this->media();
        $this->artisan('leap:images --warm')->assertSuccessful();

        // Someone typing the command to find out what it is gets an answer, not
        // a telling-off — with where things stand, which is the part `--help`
        // cannot give them.
        $this->artisan('leap:images')
            ->expectsOutputToContain('--prune')
            ->expectsOutputToContain('--dry-run')
            ->expectsOutputToContain('600, 1200')
            ->expectsOutputToContain('1 on the public disk')
            ->expectsOutputToContain('2 files')
            ->assertSuccessful();
    }

    /**
     * The link is what lets the web server answer a resized copy without PHP.
     * Missing, nothing breaks and nothing complains: the site is simply slower
     * for every size of every image on it. So the one command that looks at this
     * feature at all has to say it.
     */
    public function test_it_says_so_when_the_link_is_missing(): void
    {
        config(['leap.images.route' => 'img-not-linked']);

        $this->artisan('leap:images')
            ->expectsOutputToContain('storage:link')
            ->assertSuccessful();
    }

    /**
     * The case that bites on upgrade: the old cache is still there as a real
     * directory, and storage:link refuses to replace one.
     */
    public function test_it_says_so_when_a_real_directory_is_in_the_way(): void
    {
        config(['leap.images.route' => 'img-in-the-way']);
        mkdir(public_path('img-in-the-way'));

        try {
            $this->artisan('leap:images')
                ->expectsOutputToContain('is a real directory')
                ->assertSuccessful();
        } finally {
            rmdir(public_path('img-in-the-way'));
        }
    }

    public function test_it_says_nothing_when_the_link_is_there(): void
    {
        config(['leap.images.route' => 'img-linked']);
        symlink(config('filesystems.disks.leap-images.root'), public_path('img-linked'));

        try {
            $this->artisan('leap:images')
                ->doesntExpectOutputToContain('storage:link')
                ->assertSuccessful();
        } finally {
            unlink(public_path('img-linked'));
        }
    }

    public function test_it_says_so_when_the_feature_is_off(): void
    {
        config(['leap.images.enabled' => false]);

        $this->artisan('leap:images')
            ->expectsOutputToContain('leap.images.enabled')
            ->assertSuccessful();

        $this->artisan('leap:images --warm')->assertFailed();
    }
}
