<?php

namespace NickDeKruijk\Leap\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * robots.txt, rendered rather than served from public/.
 *
 * The Sitemap directive has to be an absolute URL and a site answers on a different
 * host per environment, so a file in git would have to hard-code one of them and be
 * wrong on the others. What it says is in resources/views/robots.blade.php, driven by
 * config('leap.robots') and publishable (tag: leap-views) for a site that wants to
 * write the whole thing by hand.
 */
class RobotsController extends Controller
{
    public function show(): Response
    {
        return response()
            ->view('leap::robots')
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
