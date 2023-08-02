<?php

namespace NickDeKruijk\Leap\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct()
    {
        Auth::shouldUse(config('leap.guard'));
    }

    /**
     * Return all modules the current user has access to
     *
     * @return Collection
     */
    public static function getModules(): Collection
    {
        // Get default modules from config
        $modules = config('leap.default_modules');

        // Find modules in app/Leap directory
        foreach (glob(app_path(config('leap.app_modules')) . '/*.php') as $file) {
            $module = 'App\\' . config('leap.app_modules') . '\\' . basename($file, '.php');
            $module = new $module;
            $modules[$module->slug] = $module;
        }

        // Sort the models by priority
        usort($modules, function ($a, $b) {
            return $a->priority > $b->priority;
        });

        // Return the modules
        return collect($modules);
    }

    /**
     * Show the module
     *
     * @param string $module
     * @return View
     */
    public function show(?string $module = null): View
    {
        $currentModule = $this->getModules()->where('slug', $module)->first();
        abort_if(!$currentModule, 404);
        return view('leap::layouts.app', compact('currentModule'));
    }
}
