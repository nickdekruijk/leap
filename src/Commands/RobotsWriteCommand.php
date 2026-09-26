<?php

namespace NickDeKruijk\Leap\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use NickDeKruijk\Leap\Classes\RobotsFile;
use Throwable;

/**
 * Writes public/robots.txt (see RobotsFile).
 *
 * Registered with optimizes() in the service provider, so php artisan optimize in a
 * deploy script runs this and the file is as current as the cached config next to it.
 *
 * A command of its own rather than an option on leap:robots, because optimize calls a
 * command by name and passes no arguments. It never throws: optimize stops on an
 * exception, and a deploy is not to break over robots.txt. A failure is reported and
 * returned instead, and leap:robots --check is where it fails a deploy on purpose.
 */
class RobotsWriteCommand extends Command
{
    protected $signature = 'leap:robots-write';

    protected $description = 'Write public/robots.txt, so the web server serves it without asking PHP';

    public function handle(): int
    {
        try {
            if (! config('leap.robots.enabled')) {
                RobotsFile::remove();

                return self::SUCCESS;
            }

            $this->readFreshCaches();

            // See leap:robots: without this every named route is invisible from the
            // console, and the Sitemap line would be left out.
            Route::getRoutes()->refreshNameLookups();

            // The project answers robots.txt itself. A file would take that over without
            // a word, so there is none, and one leap wrote earlier goes.
            if (($routes = RobotsFile::routes()) !== []) {
                RobotsFile::remove();
                $this->components->warn('A route answers robots.txt ('.implode(', ', $routes).'), so leap writes no file. Set leap.robots.enabled to false to say so.');

                return self::SUCCESS;
            }

            if (! RobotsFile::write()) {
                $this->components->error('A hand-written file at '.RobotsFile::path().' was left alone. Delete it to have leap write one, or publish the robots view (tag: leap-views) to change what leap writes.');

                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->components->error('robots.txt was not written: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Written '.RobotsFile::path().'.');

        return self::SUCCESS;
    }

    /**
     * Read the route and config caches again, when there are any.
     *
     * Under optimize this command runs in the process that booted from the caches of
     * the previous deploy. route:cache and config:cache have just written new ones in
     * that same run, but the router and the config in memory are still the old ones.
     * On the first deploy after upgrading from 1.17, that old router still had leap's
     * own route on /robots.txt, so no file was written and the address stayed a 404
     * until the next deploy; a sitemap route added in the same deploy was missing
     * from the Sitemap line the same way.
     *
     * The files themselves are asked, not routesAreCached() or configurationIsCached():
     * those remember what was there when the process booted.
     */
    protected function readFreshCaches(): void
    {
        if (is_file($routes = $this->laravel->getCachedRoutesPath())) {
            require $routes;
        }

        if (is_file($configPath = $this->laravel->getCachedConfigPath())) {
            $config = require $configPath;

            if (isset($config['leap']['robots'])) {
                config(['leap.robots' => $config['leap']['robots']]);
            }
        }
    }
}
