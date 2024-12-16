<?php

use NickDeKruijk\Leap\Controllers\AssetController;
use NickDeKruijk\Leap\Controllers\LogoutController;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Livewire\Auth2FA as LivewireAuth2FA;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Livewire\Login;
use NickDeKruijk\Leap\Middleware\Auth2FA;
use NickDeKruijk\Leap\Middleware\LeapAuth;
use NickDeKruijk\Leap\Middleware\RequireRole;

Route::middleware('web')->prefix(config('leap.route_prefix'))->group(function () {
    // Assets, this way we don't need to publish them to public
    Route::get('leap.css', [AssetController::class, 'css'])->name('leap.css');

    // Set login and logout routes if required
    if (config('leap.auth_routes')) {
        Route::get('login', Login::class)->name('leap.login');
        Route::get('login/verify', LivewireAuth2FA::class)->name('leap.auth_2fa')->middleware([LeapAuth::class]);
        Route::post('logout', LogoutController::class)->name('leap.logout');
    }

    // All other routes require authentication and the Leap middleware
    Route::middleware([LeapAuth::class, RequireRole::class, Auth2FA::class])->group(function () {
        // Home route to redirect to after login
        Route::get(config('leap.organizations') ? '{organization?}/' : '/', [ModuleController::class, 'home'])->name('leap.home');

        // If organizations are enabled, add the {organization?} prefix to some routes
        $organizations_prefix = config('leap.organizations') ? '{organization}/' : '';

        // Register all modules routes
        foreach (ModuleController::getAllModules() as $module) {
            if ($module->getSlug()) {
                Route::get($organizations_prefix . $module->getSlug(), $module::class)->name('leap.module.' . $module->getSlug());
                if ($module::class === FileManager::class) {
                    // Filemanager download/preview route
                    Route::get($organizations_prefix . $module->getSlug() . '/download/{name}', [FileManager::class, 'download'])->name('leap.module.' . $module->getSlug() . '.download')->where('name', '(.*)');
                }
            }
        }
    });
});
