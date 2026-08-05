<?php

use NickDeKruijk\Leap\Controllers\ImageController;

// Public and unauthenticated, on purpose: these are the images of the site
// itself, and this route only ever runs for a copy the web server just failed
// to find on disk. Everything it can serve is derived from a file that is
// already public.
Route::middleware('web')
    ->get(trim((string) config('leap.images.route'), '/').'/{preset}/{path}', [ImageController::class, 'show'])
    ->where('preset', '[A-Za-z0-9_-]+')
    ->where('path', '[^\\\\]+')
    ->name('leap.image');
