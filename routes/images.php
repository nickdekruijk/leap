<?php

use NickDeKruijk\Leap\Controllers\ImageController;

// Public and unauthenticated, on purpose: these are the images of the site
// itself, and this route only ever runs for a copy the web server just failed
// to find on disk. Everything it can serve is derived from a file that is
// already public.
Route::middleware('web')
    ->get(trim((string) config('leap.images.route'), '/').'/{preset}/{path}', [ImageController::class, 'show'])
    // A base name, and optionally the format it is asked for: "1200", "og",
    // "1200.avif", "1200.fallback". One dot and no more, so the segment cannot
    // become a traversal even before ImagePreset::find() refuses it.
    ->where('preset', '[A-Za-z0-9_-]+(\.[A-Za-z0-9]+)?')
    ->where('path', '[^\\\\]+')
    ->name('leap.image');
