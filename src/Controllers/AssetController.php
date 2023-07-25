<?php
// Thank you Barryvdh\Debugbar for this concept!

namespace NickDeKruijk\Leap\Controllers;

use App;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Formatter\Crunched;

class AssetController extends Controller
{
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

        if ($filemtime != cache()->get('leap.css.filemtime')) {
            // Files have changed so recompile and cache
            cache()->put('leap.css.filemtime', $filemtime);

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
            cache(['leap.css' => $css], 3600);
        } else {
            // Get the cached css
            $css = cache('leap.css');
        }

        // Return the css as a response
        $response = new Response($css, 200, ['Content-Type' => 'text/css']);
        return App::isLocal() ? $response : $this->cacheResponse($response);
    }

    // Cache the response 1 year (31536000 sec)
    protected function cacheResponse(Response $response)
    {
        $response->setSharedMaxAge(31536000);
        $response->setMaxAge(31536000);
        $response->setExpires(new \DateTime('+1 year'));

        return $response;
    }
}
