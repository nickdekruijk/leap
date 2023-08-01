<?php

use NickDeKruijk\Leap\Controllers\AssetController;
use NickDeKruijk\Leap\Controllers\LogoutController;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Login;
use NickDeKruijk\Leap\Middleware\Leap;

Route::group(['middleware' => ['web']], function () {
    // Assets, this way we don't need to publish them to public
    Route::get(config('leap.route_prefix') . '/leap.css', [AssetController::class, 'css'])->name('leap.css');

    // Set login and logout routes if required
    if (config('leap.auth_routes')) {
        Route::get(config('leap.route_prefix') . '/login',  Login::class)->name('leap.login');
        Route::post(config('leap.route_prefix') . '/logout', LogoutController::class)->name('leap.logout');
    }
});

Route::group(['middleware' => ['web', Leap::class]], function () {
    Route::get(config('leap.route_prefix'), Dashboard::class)->name('leap.dashboard');
});
