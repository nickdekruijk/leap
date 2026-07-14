<?php

/*
|--------------------------------------------------------------------------
| ImageResize config for the Leap frontend template
|--------------------------------------------------------------------------
|
| Overrides the nickdekruijk/imageresize defaults with the width presets the
| template's srcset/backgrounds use, and points `originals` at Leap's public
| disk (Leap stores media on the `public` disk → storage/app/public, served at
| /storage/*). Run `php artisan storage:link` so those originals resolve.
|
| 600–1600 cover the section <img>s (max ~760px CSS = crisp at 2x); 1920/2560
| are for the full-bleed backgrounds and the slider hero on large/retina screens.
|
*/

$widths = [600, 900, 1200, 1600, 1920, 2560];

$templates = [];
foreach ($widths as $width) {
    // 'fit' caps the width and keeps the aspect ratio; the original format is
    // preserved (jpeg stays jpeg, png stays png). The tall height cap just keeps
    // very tall images from being constrained by height.
    $templates[(string) $width] = [
        'type' => 'fit',
        'width' => $width,
        'height' => $width * 3,
        'quality' => 78,
    ];
}

return [
    // The route doubles as the cache directory (public/resized). Flat rather than
    // the package default of media/resized: nothing else lives under media/, so it
    // was an empty wrapper. A URL should say what is there, not which package made
    // it, so "resized" rather than "imageresize".
    'route' => 'resized',
    'originals' => 'storage',
    'templates' => $templates,
    'quality_jpeg' => 80,
];
