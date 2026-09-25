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
}
