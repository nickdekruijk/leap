<?php

namespace NickDeKruijk\Leap\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Classes\RobotsFile;

/**
 * What a crawler is told, and what is in the way of it being told.
 *
 * robots.txt is a file php artisan optimize writes (see RobotsFile), and it is the one
 * thing about a site nobody looks at until the traffic is gone. The ways it goes wrong
 * silently: a hand-written file in public/ or a route of the project's own, both of which
 * leap leaves alone, a deploy that never runs optimize, and disallow_all, on by default outside production, so an APP_ENV that
 * is not quite right takes the whole site out of the index without an error anywhere.
 *
 * Hence --check, meant for a deploy: it says nothing when nothing is wrong and fails
 * when something is.
 */
class RobotsCommand extends Command
{
    protected $signature = 'leap:robots
        {--check : Report what is wrong and nothing else, and fail when there is something. For a deploy}';

    protected $description = 'Show the robots.txt leap writes, and what is in the way of it';

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
            $this->components->info('public/robots.txt is leap\'s and nothing is in the way.');
        }

        return $problems ? self::FAILURE : self::SUCCESS;
    }

    /**
     * What leap writes to public/robots.txt, rendered here rather than read from the
     * file, so it shows the current config even before the next optimize.
     */
    private function overview(): void
    {
        $this->newLine();

        if (! config('leap.robots.enabled')) {
            return;
        }

        foreach (explode("\n", rtrim(view('leap::robots')->render(), "\n")) as $line) {
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

        if (RobotsFile::state() === RobotsFile::FOREIGN) {
            $problems[] = 'A file at '.RobotsFile::path().' that leap did not write is in the way: leap leaves it alone, so nothing below is served. Delete it and run php artisan optimize, or add /public/robots.txt to .gitignore if it is leap\'s.';
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
            return ['leap.robots.enabled is false: leap writes no robots.txt, so /robots.txt is whatever public/ has, or a 404.'];
        }

        $notices = [];

        // The project answers it itself, so leap:robots-write stays out of the way.
        if (($routes = RobotsFile::routes()) !== []) {
            $notices[] = 'A route answers robots.txt ('.implode(', ', $routes).'), so leap writes no file and what is below is not served. Set leap.robots.enabled to false to say so.';
        } elseif (in_array(RobotsFile::state(), [RobotsFile::MISSING, RobotsFile::SKELETON], true)) {
            // A notice rather than a problem: --check may well run in a deploy before
            // optimize has.
            $notices[] = 'public/robots.txt is not leap\'s yet. Run php artisan optimize (or leap:robots-write), and make sure the deploy does.';
        }

        if (config('leap.robots.disallow_all') && ! app()->environment('production')) {
            $notices[] = 'Everything is disallowed, because APP_ENV is '.app()->environment().' and not production. Right for a copy of the site; check that the live one says production.';
        }

        $sitemap = config('leap.robots.sitemap', 'sitemap');

        if (is_string($sitemap) && $sitemap !== '' && ! str_starts_with($sitemap, 'http') && ! Route::has($sitemap)) {
            $notices[] = 'There is no route named '.$sitemap.', so the Sitemap line is left out. Name the frontend\'s sitemap route in leap.robots.sitemap, or put the URL there.';
        }

        return $notices;
    }
}
