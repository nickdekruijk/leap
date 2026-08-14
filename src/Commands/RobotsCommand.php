<?php

namespace NickDeKruijk\Leap\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * What a crawler is told, and what is in the way of it being told.
 *
 * robots.txt is served by a route (see config leap.robots), and a route is the one
 * thing about a site nobody looks at until the traffic is gone. Two ways it goes wrong
 * silently: a file in public/robots.txt is answered by the web server without PHP being
 * reached, so the route runs in tests and never in production; and disallow_all is on
 * by default outside production, so an APP_ENV that is not quite right takes the whole
 * site out of the index without an error anywhere.
 *
 * Hence --check, meant for a deploy: it says nothing when nothing is wrong and fails
 * when something is.
 */
class RobotsCommand extends Command
{
    protected $signature = 'leap:robots
        {--check : Report what is wrong and nothing else, and fail when there is something. For a deploy}';

    protected $description = 'Show the robots.txt this site serves, and what is in the way of it';

    public function handle(): int
    {
        // Route::get(...)->name(...) sets the name after the route is in the collection,
        // and the router's name lookup is only rebuilt while matching a request. Without
        // this, every named route in the project is invisible here and the sitemap would
        // be reported missing on a site that has one.
        Route::getRoutes()->refreshNameLookups();

        $problems = $this->problems();
        $notices = $this->notices();

        if (! $this->option('check')) {
            $this->overview();
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        foreach ($notices as $notice) {
            $this->components->warn($notice);
        }

        if ($this->option('check') && ! $problems && ! $notices) {
            $this->components->info('robots.txt is served by leap and nothing is in the way.');
        }

        return $problems ? self::FAILURE : self::SUCCESS;
    }

    /**
     * What a request for /robots.txt answers with, rendered here rather than fetched,
     * so this also works on a site the command's own machine cannot reach.
     */
    private function overview(): void
    {
        $this->newLine();

        if (! config('leap.robots.enabled')) {
            return;
        }

        $body = view('leap::robots')->render();

        foreach (explode("\n", rtrim($body, "\n")) as $line) {
            $this->line(str_starts_with($line, '#') ? '  <fg=gray>'.$line.'</>' : '  '.$line);
        }

        $this->newLine();
    }

    /**
     * What is broken as opposed to merely configured: an accident nobody meant, and
     * that nothing else reports. These fail --check.
     *
     * @return array<int, string>
     */
    private function problems(): array
    {
        if (! config('leap.robots.enabled')) {
            return [];
        }

        $problems = [];

        if (file_exists($file = public_path('robots.txt'))) {
            $problems[] = 'A file at '.$file.' shadows the route: the web server answers it without asking PHP, so nothing below is served. Delete it.';
        }

        // Only visible from here. In the service provider the project's own routes are
        // not loaded yet, so a duplicate cannot be spotted at the time the route is
        // registered. Which of the two answers is not said on purpose: a route
        // collection is keyed on method plus URI, so an identical address replaces what
        // was there and the project's wins, while a catch-all is a different address and
        // loses to leap's on match order. Two routes is the problem either way.
        if (($others = $this->otherRoutes()) !== []) {
            $problems[] = 'Another route answers robots.txt as well ('.implode(', ', $others).'). Only one of the two ever runs, and which one is a detail of how they were registered rather than a decision: remove one, or set leap.robots.enabled to false.';
        }

        // The default derives disallow_all from APP_ENV, so true on production means
        // somebody forced it. That is the one version of this worth failing on.
        if (config('leap.robots.disallow_all') && app()->environment('production')) {
            $problems[] = 'Everything is disallowed on production: leap.robots.disallow_all is true (LEAP_ROBOTS_DISALLOW_ALL?). Nothing on this site may be crawled.';
        }

        return $problems;
    }

    /**
     * Worth saying out loud, but somebody may well have meant it. These do not fail
     * --check: a staging deploy is not a broken deploy.
     *
     * @return array<int, string>
     */
    private function notices(): array
    {
        if (! config('leap.robots.enabled')) {
            return ['leap.robots.enabled is false: leap serves no robots.txt, so /robots.txt is whatever public/ has, or a 404.'];
        }

        $notices = [];

        if (config('leap.robots.disallow_all') && ! app()->environment('production')) {
            $notices[] = 'Everything is disallowed, because APP_ENV is '.app()->environment().' and not production. Right for a copy of the site; check that the live one says production.';
        }

        $sitemap = config('leap.robots.sitemap', 'sitemap');

        if (is_string($sitemap) && $sitemap !== '' && ! str_starts_with($sitemap, 'http') && ! Route::has($sitemap)) {
            $notices[] = 'There is no route named '.$sitemap.', so the Sitemap line is left out. Name the frontend\'s sitemap route in leap.robots.sitemap, or put the URL there.';
        }

        return $notices;
    }

    /**
     * Routes other than leap's own that also answer robots.txt.
     *
     * @return array<int, string>
     */
    private function otherRoutes(): array
    {
        $others = [];

        foreach (Route::getRoutes() as $route) {
            if (trim($route->uri(), '/') === 'robots.txt' && $route->getName() !== 'leap.robots') {
                $others[] = $route->getName() ?: $route->getActionName();
            }
        }

        return $others;
    }
}
