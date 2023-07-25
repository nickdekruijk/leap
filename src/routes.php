<?php

use NickDeKruijk\Leap\Controllers\AssetController;
Route::group(['middleware' => ['web']], function () {
    // Assets, this way we don't need to publish them to public
    Route::get(config('leap.route_prefix') . '/leap.css', [AssetController::class, 'css'])->name('leap.css');
});
