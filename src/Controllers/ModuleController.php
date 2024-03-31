<?php

namespace NickDeKruijk\Leap\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;

class ModuleController extends Controller
{
    /**
     * Return all available leap modules
     *
     * @return Collection
     */
    public static function getAllModules(): Collection
    {
        // Get default modules from config
        foreach (config('leap.default_modules') as $module) {
            $modules[] = is_string($module) ? new $module : $module;
        }

        // Find all modules in app/Leap directory
        foreach (glob(app_path(config('leap.app_modules')) . '/*.php') as $counter => $file) {
            $module = 'App\\' . config('leap.app_modules') . '\\' . basename($file, '.php');
            $module = new $module();
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
     *
     * @return Collection
     */
    public static function getModules(): Collection
    {
        $modules = static::getAllModules();

        foreach ($modules as $n => $module) {
            if (empty(Context::get('leap.permissions')[$module::class])) {
                unset($modules[$n]);
            }
        }

        return $modules;
    }

    /**
     * Redirect to the first module of the users default organization (if any)
     *
     * @return RedirectResponse
     */
    public static function home($organization = null): RedirectResponse
    {
        return redirect()->route('leap.module.' . static::getModules()->first()->getSlug(), $organization);
    }
}
