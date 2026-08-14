<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Tests\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * leap:robots exists for the two ways this feature fails without a word: a file in
 * public/ that the web server answers before PHP is reached, and a whole site
 * disallowed because APP_ENV is not what somebody thought it was. Neither shows up in
 * a test suite, a log or an error page, so something has to go looking.
 */
class RobotsCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.robots.disallow_all', false);
    }

    protected function tearDown(): void
    {
        File::delete(public_path('robots.txt'));

        parent::tearDown();
    }

    public function test_it_prints_what_a_crawler_gets(): void
    {
        $this->artisan('leap:robots')
            ->expectsOutputToContain('User-agent: GPTBot')
            ->assertSuccessful();
    }

    public function test_a_file_in_public_shadows_the_route_and_fails_the_check(): void
    {
        File::ensureDirectoryExists(public_path());
        File::put(public_path('robots.txt'), "User-agent: *\n");

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('shadows the route')
            ->assertExitCode(Command::FAILURE);
    }

    /**
     * One of the two never runs. Only a command can see that there are two: when leap's
     * route is registered the project's routes are not loaded yet.
     */
    public function test_a_second_route_on_the_same_address_fails_the_check(): void
    {
        Route::get('robots.txt', fn () => 'mine')->name('site.robots');

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('site.robots')
            ->assertExitCode(Command::FAILURE);
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

        $this->artisan('leap:robots --check')
            ->expectsOutputToContain('nothing is in the way')
            ->assertSuccessful();

        $this->artisan('leap:robots')
            ->expectsOutputToContain('Sitemap: '.route('sitemap'))
            ->assertSuccessful();
    }

    public function test_the_feature_being_off_is_reported_rather_than_rendered(): void
    {
        config()->set('leap.robots.enabled', false);

        $this->artisan('leap:robots')
            ->expectsOutputToContain('leap.robots.enabled is false')
            ->doesntExpectOutputToContain('GPTBot')
            ->assertSuccessful();
    }
}
