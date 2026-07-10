<?php

// Thank you Barryvdh\Debugbar for this concept!

namespace NickDeKruijk\Leap\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class AssetController extends Controller
{
    // Cache duration in seconds
    const CACHE_DURATION = 3600;

    /**
     * Return the css config array but check if the files exist and
     * replace with resources/css or package resources/css if available
     */
    public static function cssFiles(): array
    {
        // Get the css array from the config
        $css = config('leap.css');

        foreach ($css as $id => $file) {
            if (! file_exists($file)) {
                // Try to get the file from the app resources/css/leap directory
                $newfile = base_path('resources/css/leap/'.$file);
                if (! file_exists($newfile)) {
                    // Try to get the file from the package resources/css directory
                    $newfile = __DIR__.'/../../resources/css/'.$file;
                    if (! file_exists($newfile)) {
                        // File not found it either location, raise error
                        throw new \Exception('File not found: '.$file);
                    }
                }
                // File found, update the css array
                $css[$id] = $newfile;
            }
        }

        return $css;
    }

    /**
     * Return the filemtime of all sass/css files combined, used to check if we need to recompile
     */
    public static function cssFilemtime(): int
    {
        $filemtime = 0;

        foreach (self::cssFiles() as $file) {
            $filemtime += filemtime($file);
        }

        return $filemtime;
    }

    /**
     * Return all combined css files as single Response
     */
    public static function css(): Response
    {
        // Get the filemtime of all css files combined
        $filemtime = self::cssFilemtime();

        // Check if we need to recombine the css
        if ($filemtime !== Cache::get('leap.css.filemtime') || ! Cache::get('leap.css')) {
            // Combine all css files into a single string, with a comment noting the compile time
            $css = '/* Compiled: '.now().'*/'.PHP_EOL;
            foreach (self::cssFiles() as $file) {
                $css .= file_get_contents($file);
            }

            // Cache it
            Cache::put('leap.css', $css, self::CACHE_DURATION);
            Cache::put('leap.css.filemtime', $filemtime, self::CACHE_DURATION);
        } else {
            // Get the cached css
            $css = Cache::get('leap.css');
        }

        // Return the css as a response
        $response = new Response($css, 200, ['Content-Type' => 'text/css']);

        return self::cacheResponse($response);
    }

    /**
     * Add cache headers to the response
     */
    protected static function cacheResponse(Response $response): Response
    {
        $response->setSharedMaxAge(self::CACHE_DURATION);
        $response->setMaxAge(self::CACHE_DURATION);
        $response->setExpires(new \DateTime(self::CACHE_DURATION.' seconds'));

        return $response;
    }

    /**
     * Return a link html tag to the compiled css with filemtime as query string as cache buster
     */
    public static function cssLink(): string
    {
        return '<link rel="stylesheet" href="'.route('leap.css').'?'.self::cssFilemtime().'">';
    }

    /**
     * Append the file's mtime as a cache-busting query string to a root-relative
     * local URL (e.g. "/css/tinymce.css" → "/css/tinymce.css?v=123"). Remote URLs,
     * URLs that already carry a query string, and missing files are returned as-is.
     */
    public static function cacheBust(string $url): string
    {
        if (str_starts_with($url, '/') && ! str_contains($url, '?')) {
            $path = public_path(ltrim($url, '/'));
            if (is_file($path)) {
                return $url.'?v='.filemtime($path);
            }
        }

        return $url;
    }

    /**
     * Return a <link> tag for the configured TinyMCE content_css (cache-busted), so
     * the click-to-edit rich-text preview in the admin is styled like the editor.
     * The stylesheet must scope its rules under .tinymce (matching body_class and
     * the preview wrapper) so it does not affect the rest of the admin. Empty when
     * no content_css is configured.
     */
    public static function tinymceContentCssLink(): string
    {
        $css = config('leap.tinymce.options.content_css');
        if (! is_string($css) || $css === '') {
            return '';
        }

        return '<link rel="stylesheet" href="'.self::cacheBust($css).'">';
    }

    /**
     * Return the filemtime of the passkeys js file, used as a cache buster
     */
    public static function jsFilemtime(): int
    {
        return filemtime(__DIR__.'/../../resources/js/passkeys.js');
    }

    /**
     * Return the passkeys js file as a Response, this way we don't need to publish it to public
     */
    public static function js(): Response
    {
        $js = file_get_contents(__DIR__.'/../../resources/js/passkeys.js');

        $response = new Response($js, 200, ['Content-Type' => 'application/javascript']);

        return self::cacheResponse($response);
    }
}
