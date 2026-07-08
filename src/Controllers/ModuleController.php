<?php

namespace NickDeKruijk\Leap\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Navigation\Logout;

class ModuleController extends Controller
{
    /**
     * Return all available leap modules
     */
    public static function getAllModules(): Collection
    {
        // Get default modules from config
        foreach (config('leap.default_modules') as $module) {
            $modules[] = is_string($module) ? new $module : $module;
        }

        // Find all modules in app/Leap directory
        foreach (glob(app_path(config('leap.app_modules')).'/*.php') as $counter => $file) {
            $module = 'App\\'.config('leap.app_modules').'\\'.basename($file, '.php');
            $module = new $module;
            $module->priority = $module->priority ?: $counter + 1;
            $modules[] = $module;
        }

        // Sort the models by priority
        usort($modules, function ($a, $b): int {
            return ($a->getPriority() > $b->getPriority()) ? 1 : -1;
        });

        // Return the modules
        return collect($modules);
    }

    /**
     * Return all modules the current user has access to
     */
    public static function getModules(): Collection
    {
        $modules = static::getAllModules();

        foreach ($modules as $n => $module) {
            $permissions = Leap::context()->permissionsFor($module::class);
            if (empty($permissions['read']) && empty($permissions['all_permissions'])) {
                unset($modules[$n]);

                continue;
            }

            // While mandatory 2FA enrollment is pending, only show the profile (to enroll) and logout
            if (Leap::mustEnrollTwoFactor() && $module->getSlug() !== 'profile' && ! $module instanceof Logout) {
                unset($modules[$n]);
            }
        }

        return $modules;
    }

    /**
     * Redirect to the first module the user has access to
     */
    public static function home(): RedirectResponse
    {
        return redirect()->route('leap.module.'.static::getModules()->where('priority', '>=', -100)->first()->getSlug());
    }
}
