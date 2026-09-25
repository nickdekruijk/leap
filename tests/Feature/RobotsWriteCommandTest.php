<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NickDeKruijk\Leap\Classes\RobotsFile;
use NickDeKruijk\Leap\Tests\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * leap:robots-write puts robots.txt in public/, on every php artisan optimize. It
 * overwrites what is leap's or Laravel's skeleton, and nothing somebody wrote by hand.
 * Switched off it is RobotsDisabledTest.
 */
class RobotsWriteCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.robots.disallow_all', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(public_path());
    }

    protected function tearDown(): void
    {
        File::delete(public_path('robots.txt'));

        parent::tearDown();
    }

    public function test_it_writes_the_file_when_there_is_none(): void
    {
        $this->artisan('leap:robots-write')->assertSuccessful();

        $written = File::get(public_path('robots.txt'));

        $this->assertStringStartsWith(RobotsFile::MARKER."\n", $written);
        $this->assertStringContainsString("User-agent: *\nDisallow:\n", $written);
    }

    /**
     * The line the file is for. The route is named after the fact, which the router
     * only picks up while matching a request, so the command has to refresh the name
     * lookup itself or the line is left out.
     */
    public function test_it_includes_the_sitemap(): void
    {
        Route::get('sitemap.xml', fn () => 'xml')->name('sitemap');

        $this->artisan('leap:robots-write')->assertSuccessful();

        $this->assertStringContainsString('Sitemap: '.url('sitemap.xml')."\n", File::get(public_path('robots.txt')));
    }

    public function test_it_replaces_its_own_file(): void
    {
        File::put(public_path('robots.txt'), RobotsFile::MARKER."\nUser-agent: *\nDisallow: /old\n");

        $this->artisan('leap:robots-write')->assertSuccessful();

        $this->assertSame(RobotsFile::render(), File::get(public_path('robots.txt')));
    }

    public function test_it_replaces_the_skeleton_file(): void
    {
        File::put(public_path('robots.txt'), "User-agent: *\r\nDisallow:\r\n");

        $this->artisan('leap:robots-write')->assertSuccessful();

        $this->assertSame(RobotsFile::render(), File::get(public_path('robots.txt')));
    }

    public function test_it_leaves_a_hand_written_file_alone(): void
    {
        File::put(public_path('robots.txt'), "User-agent: *\nDisallow: /private\n");

        $this->artisan('leap:robots-write')
            ->expectsOutputToContain('left alone')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame("User-agent: *\nDisallow: /private\n", File::get(public_path('robots.txt')));
    }

    /**
     * A file is served before PHP is reached, so writing one would silently take over
     * from the project's own route. Leap writes none, and removes one it wrote earlier.
     */
    public function test_a_route_of_the_projects_own_keeps_the_address(): void
    {
        Route::get('robots.txt', fn () => 'mine')->name('site.robots');

        $this->artisan('leap:robots-write')
            ->expectsOutputToContain('site.robots')
            ->assertSuccessful();
        $this->assertFileDoesNotExist(public_path('robots.txt'));

        File::put(public_path('robots.txt'), RobotsFile::render());
        $this->artisan('leap:robots-write')->assertSuccessful();
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    /**
     * What makes this work without anybody changing a deploy script.
     */
    public function test_optimize_runs_it(): void
    {
        $this->assertContains('leap:robots-write', ServiceProvider::$optimizeCommands);
    }
}
