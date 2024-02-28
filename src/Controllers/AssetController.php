<?php
// Thank you Barryvdh\Debugbar for this concept!

namespace NickDeKruijk\Leap\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

class AssetController extends Controller
{
    // Cache duration in seconds
    const CACHE_DURATION = 3600;

    /**
     * Return the css config array but check if the files exist and
     * replace with resources/css or package resources/css if available
     *
     * @return array
     */
    public static function cssFiles(): array
    {
        // Get the css array from the config
        $css = config('leap.css');

        foreach ($css as $id => $file) {
            if (!file_exists($file)) {
                // Try to get the file from the app resources/css directory
                $newfile = base_path('resources/css/' . $file);
                if (!file_exists($newfile)) {
                    // Try to get the file from the package resources/css directory
                    $newfile = __DIR__ . '/../../resources/css/' . $file;
                    if (!file_exists($newfile)) {
                        // File not found it either location, raise error
                        throw new \Exception('File not found: ' . $file);
                    }
                }
                // File found, update the css array
                $css[$id] = $newfile;
            }
        }

        return $css;
    }

    /**
     * Return the filemtime of all css files combined, used to check if we need to recompile
     *
     * @return integer
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
     * Return all compiled css files as single Response
     *
     * @return Response
     */
    public static function css(): Response
    {
        // Calculate total filemtime of all sass/css files to check if we need to recompile
        $filemtime = self::cssFilemtime();

        // Check if we need to recompile the css
        if ($filemtime != Cache::get('leap.css.filemtime') || !Cache::get('leap.css')) {
            $scss = new Compiler();
            $scss->setImportPaths([__DIR__ . '/../../resources/css']);
            $scss->setOutputStyle(OutputStyle::COMPRESSED);

            // Combine all sass files into a single string
            $sass = '';
            foreach (self::cssFiles() as $file) {
                $sass .= file_get_contents($file);
            }

            // Compile the sass into css and add a comment with the compile time
            $css = '/* Compiled: ' . now() . '*/' . PHP_EOL . $scss->compileString($sass)->getCss();

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
     *
     * @param Response $response
     * @return Response
     */
    protected static function cacheResponse(Response $response): Response
    {
        $response->setSharedMaxAge(self::CACHE_DURATION);
        $response->setMaxAge(self::CACHE_DURATION);
        $response->setExpires(new \DateTime(self::CACHE_DURATION . ' seconds'));

        return $response;
    }

    /**
     * Return a link html tag to the compiled css with filemtime as query string as cache buster
     *
     * @return string
     */
    public static function cssLink(): string
    {
        return '<link rel="stylesheet" href="' . route('leap.css') . '?' . self::cssFilemtime() . '">';
    }
}
