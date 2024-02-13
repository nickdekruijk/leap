<?php
// Thank you Barryvdh\Debugbar for this concept!

namespace NickDeKruijk\Leap\Controllers;

use App;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

class AssetController extends Controller
{
    // Cache duration in seconds
    const CACHE_DURATION = 3600;

    // Return all stylesheet files as one
    public function css()
    {
        // Calculate total filemtime of all sass/css files to check if we need to recompile
        $filemtime = 0;

        foreach (config('leap.css') as $file) {
            $filemtime += filemtime($file);
        }

        // Paths to look for @import files and to calculate total filemtime
        $paths = [__DIR__ . '/../../resources/css'];

        foreach ($paths as $path) {
            foreach (glob($path . '/*.scss') as $file) {
                $filemtime += filemtime($file);
            }
        }

        // Add names of the files to the filemtime so it changes when the css config array changes
        $filemtime .= implode(',', config('leap.css'));

        // Check if we need to recompile the css
        if ($filemtime != Cache::get('leap.css.filemtime') || !Cache::get('leap.css')) {
            $scss = new Compiler();
            $scss->setImportPaths($paths);
            $scss->setOutputStyle(OutputStyle::COMPRESSED);

            // Combine all sass files into a single string
            $sass = '';
            foreach (config('leap.css') as $file) {
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
        return App::isLocal() ? $response : $this->cacheResponse($response);
    }

    // Cache the response 1 hour (3600 sec)
    protected function cacheResponse(Response $response)
    {
        $response->setSharedMaxAge(self::CACHE_DURATION);
        $response->setMaxAge(self::CACHE_DURATION);
        $response->setExpires(new \DateTime('+1 hour'));

        return $response;
    }
}
