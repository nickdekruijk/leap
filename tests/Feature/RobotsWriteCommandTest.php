<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
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
        File::delete([public_path('robots.txt'), public_path('.gitignore'), $this->app->getCachedRoutesPath(), $this->app->getCachedConfigPath()]);

        parent::tearDown();
    }

    public function test_it_writes_the_file_when_there_is_none(): void
    {
        $this->artisan('leap:robots-write')->assertSuccessful();

        $written = File::get(public_path('robots.txt'));

        $this->assertStringStartsWith("User-agent: *\nDisallow:\n", $written);
    }

    /**
     * Anyone can read robots.txt, so it names nothing a visitor has no business knowing:
     * not the software behind the site, and not that this is a copy of it.
     */
    public function test_it_says_nothing_about_the_site_beyond_the_directives(): void
    {
        File::put(public_path('.gitignore'), "/robots.txt\n");

        foreach ([false, true] as $disallowAll) {
            config()->set('leap.robots.disallow_all', $disallowAll);

            $this->artisan('leap:robots-write')->assertSuccessful();

            $written = File::get(public_path('robots.txt'));

            $this->assertDoesNotMatchRegularExpression('/leap|laravel|artisan|production/i', $written);
            $this->assertDoesNotMatchRegularExpression('/^#/m', $written);
        }
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
        File::put(public_path('robots.txt'), RobotsFile::LEGACY_MARKER."\nUser-agent: *\nDisallow: /old\n");

        $this->artisan('leap:robots-write')->assertSuccessful();

        $this->assertSame(RobotsFile::render(), File::get(public_path('robots.txt')));
    }

    public function test_it_replaces_the_skeleton_file(): void
    {
        File::put(public_path('robots.txt'), "User-agent: *\r\nDisallow:\r\n");

        $this->artisan('leap:robots-write')->assertSuccessful();

        $this->assertSame(RobotsFile::render(), File::get(public_path('robots.txt')));
    }

    /**
     * The .gitignore line is what says the file is leap's: a hand-written robots.txt is
     * in git, and this one never is.
     */
    public function test_with_the_gitignore_line_it_replaces_whatever_is_there(): void
    {
        File::put(public_path('.gitignore'), "/robots.txt\n");
        File::put(public_path('robots.txt'), "User-agent: *\nDisallow: /written-by-an-older-config\n");

        $this->artisan('leap:robots-write')->assertSuccessful();

        $this->assertSame(RobotsFile::render(), File::get(public_path('robots.txt')));
    }

    /**
     * Without the .gitignore line a file leap wrote is still recognised as long as it
     * says what leap would write, so a second deploy with the same config passes.
     */
    public function test_without_the_gitignore_line_its_unchanged_file_is_still_its_own(): void
    {
        $this->artisan('leap:robots-write')->assertSuccessful();
        $this->artisan('leap:robots-write')->assertSuccessful();
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
     * Under optimize the process booted from the previous deploy's route cache, which
     * after an upgrade from 1.17 still held leap's own route on /robots.txt. route:cache
     * has just written the new one, and that is the one that counts.
     */
    public function test_under_optimize_it_reads_the_route_cache_that_was_just_written(): void
    {
        Route::get('robots.txt', fn () => 'old')->name('leap.robots');

        $fresh = new RouteCollection;
        $fresh->add(new RoutingRoute(['GET', 'HEAD'], 'sitemap.xml', ['uses' => 'SitemapController@index', 'as' => 'sitemap']));
        File::put($this->app->getCachedRoutesPath(), "<?php\n\napp('router')->setCompiledRoutes(".var_export($fresh->compile(), true).');');

        $this->artisan('leap:robots-write')->assertSuccessful();

        $this->assertStringContainsString('Sitemap: '.url('sitemap.xml')."\n", File::get(public_path('robots.txt')));
    }

    /**
     * The same for config:cache: a change to leap.robots goes out with the deploy that
     * brings it, not with the one after.
     */
    public function test_under_optimize_it_reads_the_config_cache_that_was_just_written(): void
    {
        $robots = array_merge(config('leap.robots'), ['disallow' => ['/fresh']]);
        File::put($this->app->getCachedConfigPath(), '<?php return '.var_export(['leap' => ['robots' => $robots]], true).';');

        $this->artisan('leap:robots-write')->assertSuccessful();

        $this->assertStringContainsString("Disallow: /fresh\n", File::get(public_path('robots.txt')));
    }

    /**
     * What makes this work without anybody changing a deploy script.
     */
    public function test_optimize_runs_it(): void
    {
        $this->assertContains('leap:robots-write', ServiceProvider::$optimizeCommands);
    }
}
