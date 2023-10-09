<?php

use NickDeKruijk\Leap\Controllers\AssetController;
use NickDeKruijk\Leap\Controllers\LogoutController;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Livewire\Auth2FA as LivewireAuth2FA;
use NickDeKruijk\Leap\Livewire\Login;
use NickDeKruijk\Leap\Middleware\Auth2FA;
use NickDeKruijk\Leap\Middleware\Leap;
use NickDeKruijk\Leap\Middleware\RequireRole;

Route::middleware('web')->prefix(config('leap.route_prefix'))->group(function () {
    // Assets, this way we don't need to publish them to public
    Route::get('leap.css', [AssetController::class, 'css'])->name('leap.css');

    // Set login and logout routes if required
    if (config('leap.auth_routes')) {
        Route::get('login', Login::class)->name('leap.login');
        Route::get('login/verify', LivewireAuth2FA::class)->name('leap.auth_2fa')->middleware([Leap::class, RequireRole::class]);
        Route::post('logout', LogoutController::class)->name('leap.logout');
    }

    // All other routes require authentication and the Leap middleware
    Route::middleware([Leap::class, RequireRole::class, Auth2FA::class])->group(function () {
        // Get all available modules
        $modules = ModuleController::getAllModules();

        // Set the home route to the first module
        Route::get('/', $modules->first()::class)->name('leap.home');

        // Register all modules routes
        foreach ($modules as $n => $module) {
            Route::get($module->getSlug(), $module::class)->name('leap.module.' . $module->getSlug());
        }
    });
});
