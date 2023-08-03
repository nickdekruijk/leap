<?php

use NickDeKruijk\Leap\Controllers\AssetController;
use NickDeKruijk\Leap\Controllers\LogoutController;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Livewire\Auth2FA as LivewireAuth2FA;
use NickDeKruijk\Leap\Livewire\Login;
use NickDeKruijk\Leap\Middleware\Auth2FA;
use NickDeKruijk\Leap\Middleware\Leap;

Route::middleware('web')->prefix(config('leap.route_prefix'))->group(function () {
    // Assets, this way we don't need to publish them to public
    Route::get('leap.css', [AssetController::class, 'css'])->name('leap.css');

    // Set login and logout routes if required
    if (config('leap.auth_routes')) {
        Route::get('login', Login::class)->name('leap.login');
        Route::get('login/verify', LivewireAuth2FA::class)->name('leap.auth_2fa')->middleware(Leap::class);
        Route::post('logout', LogoutController::class)->name('leap.logout');
    }

    // All other routes require authentication and the Leap middleware
    Route::get('{module?}', [ModuleController::class, 'show'])->name('leap.module')->middleware([Leap::class, Auth2FA::class]);
});
