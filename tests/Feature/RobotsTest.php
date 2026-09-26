<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * robots.txt: what a crawler is told before it reads anything else.
 *
 * Here it is only about what the view says. Getting it into public/ is
 * RobotsWriteCommandTest, and what is in the way of that is RobotsCommandTest.
 */
class RobotsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The suite runs outside production, where the default is to disallow
        // everything. Every test but the two about that key wants the other stance.
        $app['config']->set('leap.robots.disallow_all', false);
    }

    public function test_it_allows_everything_by_default(): void
    {
        $robots = $this->robots();

        $this->assertStringContainsString("User-agent: *\nDisallow:\n", $robots);
        $this->assertDoesNotMatchRegularExpression('#^Disallow: /$#m', $robots);
    }

    /**
     * Allowed, the answer engine crawlers fall under * like everyone else. A group of
     * their own saying the same would only be noise, and a list every site shares.
     */
    public function test_allowed_answer_engine_crawlers_are_not_named(): void
    {
        $this->assertSame("User-agent: *\nDisallow:\n", $this->robots());
    }

    /**
     * The one line the file is really for. AI crawlers follow fewer links than Google
     * does and lean on the sitemap instead, so a robots.txt that does not name it
     * leaves them to guess.
     */
    public function test_it_points_at_the_sitemap_route_of_the_frontend(): void
    {
        Route::get('sitemap.xml', fn () => 'xml')->name('sitemap');

        $robots = $this->robots();

        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $robots);
        $this->assertStringContainsString('http', $robots);
    }

    /**
     * That route is the project's and not leap's, so it may well not be there.
     */
    public function test_without_that_route_the_line_is_left_out(): void
    {
        $this->assertFalse(Route::has('sitemap'));

        $this->assertStringNotContainsString('Sitemap:', $this->robots());
    }

    public function test_the_sitemap_can_be_a_literal_url_or_switched_off(): void
    {
        config()->set('leap.robots.sitemap', 'https://elsewhere.test/sitemap.xml');
        $this->assertStringContainsString('Sitemap: https://elsewhere.test/sitemap.xml', $this->robots());

        config()->set('leap.robots.sitemap', false);
        $this->assertStringNotContainsString('Sitemap:', $this->robots());
    }

    /**
     * The shipped default, read from the config file itself: every environment but
     * production is closed. This is the one key whose default decides whether a live
     * site is found at all, so it is asserted both ways.
     */
    public function test_the_shipped_default_is_closed_outside_production(): void
    {
        $config = __DIR__.'/../../config/leap.php';
        $original = $_ENV['APP_ENV'] ?? null;

        try {
            $_ENV['APP_ENV'] = 'staging';
            $this->assertTrue((require $config)['robots']['disallow_all']);

            $_ENV['APP_ENV'] = 'production';
            $this->assertFalse((require $config)['robots']['disallow_all']);
        } finally {
            if ($original === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $original;
            }
        }
    }

    public function test_disallow_all_closes_the_site_and_offers_no_sitemap(): void
    {
        Route::get('sitemap.xml', fn () => 'xml')->name('sitemap');
        config()->set('leap.robots.disallow_all', true);

        $robots = $this->robots();

        $this->assertStringContainsString("User-agent: *\nDisallow: /\n", $robots);
        $this->assertStringNotContainsString('Sitemap:', $robots);
        $this->assertStringNotContainsString('GPTBot', $robots);
    }

    public function test_disallowed_paths_go_under_the_one_group(): void
    {
        config()->set('leap.robots.disallow', ['/zoeken', '/export']);

        $this->assertSame("User-agent: *\nDisallow: /zoeken\nDisallow: /export\n", $this->robots());
    }

    public function test_the_answer_engines_can_be_kept_out_or_left_unmentioned(): void
    {
        config()->set('leap.robots.ai_crawlers', 'disallow');
        $robots = $this->robots();
        $this->assertStringContainsString("User-agent: GPTBot\n", $robots);
        $this->assertStringContainsString("User-agent: Diffbot\nDisallow: /\n", $robots);

        config()->set('leap.robots.ai_crawlers', 'omit');
        $omitted = $this->robots();
        config()->set('leap.robots.ai_crawlers', 'allow');
        $this->assertSame($this->robots(), $omitted);
        $this->assertStringNotContainsString('GPTBot', $omitted);
    }

    /**
     * The way out for a site that wants to write the file itself.
     */
    public function test_a_published_view_replaces_it(): void
    {
        $path = resource_path('views/vendor/leap');
        mkdir($path, 0777, true);
        file_put_contents($path.'/robots.blade.php', "User-agent: *\nDisallow: /nope\n");

        try {
            $this->assertStringContainsString('Disallow: /nope', $this->robots());
        } finally {
            unlink($path.'/robots.blade.php');
            rmdir($path);
        }
    }

    /**
     * Directives and nothing else, in every setting: a comment is readable by anyone and
     * would say which software wrote the file, or that this is not the production site.
     */
    public function test_it_holds_no_comments(): void
    {
        foreach ([[false, 'allow'], [false, 'disallow'], [false, 'omit'], [true, 'allow']] as [$disallowAll, $aiCrawlers]) {
            config()->set('leap.robots.disallow_all', $disallowAll);
            config()->set('leap.robots.ai_crawlers', $aiCrawlers);

            $this->assertDoesNotMatchRegularExpression('/^#/m', $this->robots());
        }
    }

    /**
     * As the commands render it. A route named after the fact is only in the name
     * lookup once that is refreshed, which the commands do and a test has to as well.
     */
    private function robots(): string
    {
        Route::getRoutes()->refreshNameLookups();

        return view('leap::robots')->render();
    }
}
