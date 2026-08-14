<?php

use NickDeKruijk\Leap\Controllers\RobotsController;

// Public and unauthenticated, at the root of the site: robots.txt has to answer at
// /robots.txt and nowhere else, and it is the first thing a crawler asks for.
//
// A file in public/robots.txt wins over this route without saying so, because the web
// server answers it before PHP is ever reached. `php artisan leap:robots` reports that.
//
// Named leap.robots rather than robots, so a project with a robots route of its own can
// still cache its routes: two routes with one name is an error there.
Route::middleware('web')
    ->get('robots.txt', [RobotsController::class, 'show'])
    ->name('leap.robots');
