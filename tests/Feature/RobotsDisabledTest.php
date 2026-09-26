<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Classes\RobotsFile;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Leap claims public/robots.txt, which is a thing to be able to take back: a project
 * that answers /robots.txt itself, or keeps a file of its own, should be able to switch
 * leap off rather than work around it.
 */
class RobotsDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.robots.enabled', false);
    }

    protected function tearDown(): void
    {
        File::delete([public_path('robots.txt'), public_path('.gitignore')]);

        parent::tearDown();
    }

    /**
     * Off on a site where optimize wrote one earlier: that file goes, a file of the
     * project's own stays.
     */
    public function test_optimize_removes_leaps_file_and_nothing_else(): void
    {
        File::ensureDirectoryExists(public_path());

        File::put(public_path('robots.txt'), RobotsFile::LEGACY_MARKER."\nUser-agent: *\n");
        $this->artisan('leap:robots-write')->assertSuccessful();
        $this->assertFileDoesNotExist(public_path('robots.txt'));

        File::put(public_path('robots.txt'), "User-agent: *\nDisallow: /private\n");
        $this->artisan('leap:robots-write')->assertSuccessful();
        $this->assertFileExists(public_path('robots.txt'));
    }

    /**
     * Off means the project's own route answers, and leap has nothing to say about it.
     */
    public function test_the_project_can_answer_it_instead(): void
    {
        Route::get('robots.txt', fn () => 'mine');

        $this->assertSame('mine', $this->get('/robots.txt')->getContent());

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('leap.robots.enabled is false')
            ->assertSuccessful();
    }
}
