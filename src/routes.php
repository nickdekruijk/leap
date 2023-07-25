<?php

Route::group(['middleware' => ['web']], function () {
    // Assets, this way we don't need to publish them to public
    Route::get(config('leap.route_prefix') . '/leap.css', 'NickDeKruijk\Leap\Controllers\AssetController@css')->name('leap.css');
});
