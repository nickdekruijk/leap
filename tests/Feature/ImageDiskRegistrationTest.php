<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\ServiceProvider;
use NickDeKruijk\Leap\Tests\ImageTestCase;

class ImageDiskRegistrationTest extends ImageTestCase
{
    public function test_leap_defines_the_disk_itself(): void
    {
        // So turning the feature on is one config key, not a disk a project has
        // to add to config/filesystems.php by hand.
        $this->assertSame([
            'driver' => 'local',
            'root' => storage_path('app/leap-images'),
            'url' => '/img',
            'visibility' => 'public',
            'serve' => false,
            'throw' => false,
        ], config('filesystems.disks.leap-images'));
    }

    public function test_the_copies_live_in_storage_and_not_in_public(): void
    {
        // A release-based deploy builds a new public/ every time, so a cache
        // kept there is thrown away on every deploy and regenerated one visitor
        // at a time. storage/ is shared between releases.
        $this->assertStringNotContainsString(public_path(), config('filesystems.disks.leap-images.root'));
    }

    /**
     * And not in storage/app/public either, which already has a link of its own:
     * every copy would then answer at /storage/img/... as well as at /img/...,
     * and a search engine finds the same picture at two addresses.
     */
    public function test_the_copies_are_not_reachable_through_the_public_storage_link(): void
    {
        $this->assertStringNotContainsString(storage_path('app/public'), config('filesystems.disks.leap-images.root'));
    }

    public function test_storage_link_is_told_about_the_link(): void
    {
        // Which is what lets the web server answer a resized copy off disk. The
        // disk is registered from boot(), so this is set by the time any command
        // reads it.
        $this->assertSame(storage_path('app/leap-images'), config('filesystems.links')[public_path('img')] ?? null);
    }

    public function test_the_link_follows_the_configured_route(): void
    {
        config(['leap.images.route' => 'resized', 'filesystems.disks.leap-images' => null]);

        $this->app->register(ServiceProvider::class, true);

        $this->assertSame(storage_path('app/leap-images'), config('filesystems.links')[public_path('resized')] ?? null);
    }

    public function test_a_link_the_project_declared_itself_is_left_alone(): void
    {
        config([
            'filesystems.disks.leap-images' => null,
            'filesystems.links' => [public_path('img') => '/somewhere/else'],
        ]);

        $this->app->register(ServiceProvider::class, true);

        $this->assertSame('/somewhere/else', config('filesystems.links')[public_path('img')]);
    }

    /**
     * A project that defines the disk itself is usually after a setting on it,
     * not after giving up the link. Without one the site serves every resized
     * image through PHP and nothing says so, which is too quiet a way to lose
     * the whole point of the feature.
     */
    public function test_a_disk_the_project_defined_itself_still_gets_a_link(): void
    {
        config([
            'filesystems.disks.leap-images' => [
                'driver' => 'local',
                'root' => storage_path('app/somewhere-else'),
                'url' => '/img',
            ],
            'filesystems.links' => [],
        ]);

        $this->app->register(ServiceProvider::class, true);

        // Its own root, not leap's: the project said where the files are.
        $this->assertSame(storage_path('app/somewhere-else'), config('filesystems.links')[public_path('img')] ?? null);
    }

    /**
     * The two cases with nothing to link: a driver with no directory behind it,
     * and a directory the web server already reaches where it stands.
     */
    public function test_a_remote_disk_gets_no_link(): void
    {
        config([
            'filesystems.disks.leap-images' => ['driver' => 's3', 'bucket' => 'copies'],
            'filesystems.links' => [],
        ]);

        $this->app->register(ServiceProvider::class, true);

        $this->assertSame([], config('filesystems.links'));
    }

    public function test_a_disk_already_rooted_in_public_gets_no_link(): void
    {
        config([
            'filesystems.disks.leap-images' => ['driver' => 'local', 'root' => public_path('img'), 'url' => '/img'],
            'filesystems.links' => [],
        ]);

        $this->app->register(ServiceProvider::class, true);

        $this->assertSame([], config('filesystems.links'));
    }
}
