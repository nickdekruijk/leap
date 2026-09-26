<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Classes\RobotsFile;
use NickDeKruijk\Leap\Tests\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * leap:robots exists for the ways this feature fails without a word: a hand-written file
 * in public/ that leap leaves alone, a deploy that never writes leap's, and a whole site
 * disallowed because APP_ENV is not what somebody thought it was. None of them shows up
 * in a test suite, a log or an error page, so something has to go looking.
 */
class RobotsCommandTest extends TestCase
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
        File::delete([public_path('robots.txt'), public_path('.gitignore')]);

        parent::tearDown();
    }

    public function test_it_prints_what_a_crawler_gets(): void
    {
        $this->artisan('leap:robots')
            ->expectsOutputToContain('User-agent: *')
            ->assertSuccessful();
    }

    public function test_a_hand_written_file_fails_the_check(): void
    {
        File::put(public_path('robots.txt'), "User-agent: *\nDisallow: /private\n");

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('is in the way')
            ->assertExitCode(Command::FAILURE);
    }

    /**
     * Said and not failed on: --check may run in a deploy before optimize has.
     */
    public function test_no_file_yet_is_reported_but_passes(): void
    {
        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('not leap\'s yet')
            ->assertSuccessful();
    }

    /**
     * With the defaults and no sitemap leap writes exactly what the skeleton says, so
     * this needs a config that says more.
     */
    public function test_the_skeleton_file_is_reported_but_passes(): void
    {
        config()->set('leap.robots.disallow', ['/zoeken']);
        File::put(public_path('robots.txt'), "User-agent: *\nDisallow:\n");

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('not leap\'s yet')
            ->assertSuccessful();
    }

    /**
     * The project answers robots.txt itself and leap stays out of the way, which is
     * worth saying but not broken.
     */
    public function test_a_route_of_the_projects_own_is_reported_but_passes(): void
    {
        Route::get('robots.txt', fn () => 'mine')->name('site.robots');

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('site.robots')
            ->doesntExpectOutputToContain('not leap\'s yet')
            ->assertSuccessful();
    }

    /**
     * A staging deploy is not a broken deploy, so this is said and not failed on.
     */
    public function test_a_closed_site_outside_production_is_reported_but_passes(): void
    {
        config()->set('leap.robots.disallow_all', true);

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('Everything is disallowed')
            ->assertSuccessful();
    }

    public function test_a_closed_site_on_production_fails_the_check(): void
    {
        config()->set('leap.robots.disallow_all', true);
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('production')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_a_sitemap_route_that_does_not_exist_is_reported(): void
    {
        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('There is no route named sitemap')
            ->assertSuccessful();
    }

    /**
     * Also the regression test for a route named the way every project names one: with
     * ->name() after the fact, which the router's name lookup only picks up while
     * matching a request. Without a refresh the command reports a missing sitemap on a
     * site that has one, and prints a robots.txt the site does not serve.
     */
    public function test_it_says_so_when_nothing_is_wrong(): void
    {
        Route::get('sitemap.xml', fn () => 'xml')->name('sitemap');
        Route::getRoutes()->refreshNameLookups();
        File::put(public_path('robots.txt'), RobotsFile::render());
        File::put(public_path('.gitignore'), "/robots.txt\n");

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('nothing is in the way')
            ->assertSuccessful();

        $this->artisan('leap:robots')
            ->expectsOutputToContain('Sitemap: '.route('sitemap'))
            ->assertSuccessful();
    }
}
