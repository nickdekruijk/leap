<?php
// Thank you Barryvdh\Debugbar for this concept!

namespace NickDeKruijk\Leap\Controllers;

use App;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Formatter\Crunched;

class AssetController extends Controller
{
    // Cache duration in seconds
    const CACHE_DURATION = 3600;

    // Return all stylesheet files as one
    public function css()
    {
        // The css/sass files to combine and compile
        $files = [
            __DIR__ . '/../../resources/css/_base.scss',
            __DIR__ . '/../../resources/css/' . config('leap.theme') . '.scss',
        ];

        // Calculate totol filemtime to determine if css should be recompiled
        $filemtime = 0;
        foreach ($files as $file) {
            $filemtime += filemtime($file);
        }
        if ($filemtime != Cache::get('leap.css.filemtime') || Cache::get('leap.css')) {
            // Files have changed so recompile and cache
            Cache::put('leap.css.filemtime', $filemtime, self::CACHE_DURATION);

            // Combine all files into one input string
            $input = '';
            foreach ($files as $file) {
                $input .= file_get_contents($file);
            }

            // Compile the input string to css
            $scss = new Compiler();
            $scss->setFormatter(new Crunched());
            $css = $scss->compile($input);

            // Cache it
            Cache::put('leap.css', $css, self::CACHE_DURATION);
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
