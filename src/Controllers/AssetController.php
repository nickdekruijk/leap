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
        // The pico file to use
        $pico_file = '../vendor/picocss/pico/css/pico.min.css';

        // The css/sass file to compile, use theme file if it exists otherwise use file from resources/css
        $theme_file = file_exists(config('leap.theme')) ? config('leap.theme') : __DIR__ . '/../../resources/css/' . config('leap.theme') . '.scss';

        // Paths to look for @import files and to calculate total filemtime
        $paths = [__DIR__ . '/../../resources/css'];

        // Calculate totol filemtime of pico file, theme file and all scss files in the paths to determine if css should be recompiled
        $filemtime = filemtime($pico_file) + filemtime($theme_file);
        foreach ($paths as $path) {
            foreach (glob($path . '/*.scss') as $file) {
                $filemtime += filemtime($file);
            }
        }

        // Check if we need to recompile the css
        if ($filemtime != Cache::get('leap.css.filemtime') || !Cache::get('leap.css')) {
            // Compile the input string to css
            $scss = new Compiler();
            $scss->setImportPaths($paths);
            $scss->setOutputStyle(OutputStyle::COMPRESSED);
            $css = '/* Compiled: ' . now() . '*/' . PHP_EOL . $scss->compileString(file_get_contents($pico_file) . PHP_EOL . file_get_contents($theme_file))->getCss();

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
