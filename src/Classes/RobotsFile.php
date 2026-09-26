<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Facades\Route;

/**
 * public/robots.txt, written by php artisan optimize (leap:robots-write).
 *
 * Not a file in git, because the Sitemap line has to be an absolute URL and a site
 * answers on a different host per environment. Not a route either, because a web server
 * serves a file with a 200 whatever its config does with PHP, and the nginx config Forge
 * and Herd ship does not let a route at /robots.txt through with one.
 *
 * What it says is resources/views/robots.blade.php, driven by config('leap.robots'):
 * directives only. Nothing in it names leap or Laravel, because anyone can read it.
 * Written from the console, so its URLs come from APP_URL.
 *
 * The /public/robots.txt line in .gitignore is what makes the file leap's: a robots.txt
 * somebody wrote by hand is in git, leap's never is. With that line leap replaces
 * whatever is there. Without it, only a missing file, Laravel's skeleton, one from
 * 1.18.0 or 1.18.1, or one that already says what leap would write. A robots.txt
 * somebody wrote by hand is left alone, and so is a project
 * that answers /robots.txt from a route of its own: a file would be served before PHP is
 * reached and that route would never run.
 */
class RobotsFile
{
    /**
     * The first line 1.18.0 and 1.18.1 wrote, so those files are still recognised and
     * replaced by one that does not give the site away.
     */
    public const LEGACY_MARKER = '# Written by php artisan leap:robots-write, which php artisan optimize runs. Edit config/leap.php or the published robots view, not this file.';

    public const MISSING = 'missing';

    public const OURS = 'ours';

    public const SKELETON = 'skeleton';

    public const FOREIGN = 'foreign';

    public static function path(): string
    {
        return public_path('robots.txt');
    }

    public static function render(): string
    {
        return view('leap::robots')->render();
    }

    /**
     * Whose file is at public/robots.txt, if any.
     */
    public static function state(): string
    {
        if (! is_file(self::path())) {
            return self::MISSING;
        }

        $contents = (string) file_get_contents(self::path());

        if ($contents === self::render()) {
            return self::OURS;
        }

        // Laravel's skeleton ships "User-agent: *" and an empty "Disallow:", which allows
        // everything and says nothing. Replacing it takes nothing away from anyone.
        if (preg_replace('/\s+/', ' ', trim($contents)) === 'User-agent: * Disallow:') {
            return self::SKELETON;
        }

        if (self::gitignored() || str_starts_with($contents, self::LEGACY_MARKER)) {
            return self::OURS;
        }

        return self::FOREIGN;
    }

    /**
     * @return bool false when a hand-written file is in the way and was left alone
     */
    public static function write(): bool
    {
        if (self::state() === self::FOREIGN) {
            return false;
        }

        file_put_contents(self::path(), self::render());

        return true;
    }

    /**
     * Whether .gitignore, at the root or in public/, names public/robots.txt. Read from
     * the files rather than asked of git: a server need not have git, and the release
     * directory of a zero-downtime deploy has the .gitignore all the same.
     */
    public static function gitignored(): bool
    {
        $patterns = [
            base_path('.gitignore') => '#^\s*/?public/robots\.txt\s*$#m',
            public_path('.gitignore') => '#^\s*/?robots\.txt\s*$#m',
        ];

        foreach ($patterns as $gitignore => $pattern) {
            if (is_file($gitignore) && preg_match($pattern, (string) file_get_contents($gitignore))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The project's routes on /robots.txt. Leap has none of its own, so any route there
     * is somebody's answer to the same question. Refresh the router's name lookup first
     * to see route names.
     *
     * @return array<int, string>
     */
    public static function routes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (trim($route->uri(), '/') === 'robots.txt') {
                $routes[] = $route->getName() ?: $route->getActionName();
            }
        }

        return $routes;
    }

    /**
     * Remove the file, but only when leap wrote it.
     */
    public static function remove(): void
    {
        if (self::state() === self::OURS) {
            unlink(self::path());
        }
    }
}
