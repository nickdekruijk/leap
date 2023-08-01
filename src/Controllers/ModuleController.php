<?php

namespace NickDeKruijk\Leap\Controllers;

use App\Http\Controllers\Controller;
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
     * @return array
     */
    public static function getModules(): array
    {
        $modules = [];

        // Find modules in app/Leap directory
        foreach (glob(app_path(config('leap.app_modules')) . '/*.php') as $file) {
            $module = 'App\\' . config('leap.app_modules') . '\\' . basename($file, '.php');
            $module = new $module;
            $modules[$module->getSlug()] = $module;
        }

        // Sort the models by priority
        uksort($modules, function ($a, $b) {
            return $a->priority < $b->priority;
        });

        // Return the modules
        return $modules;
    }

    /**
     * Show the module
     *
     * @param string $module
     * @return View
     */
    public function show(string $module): View
    {
        $modules = $this->getModules();
        abort_if(empty($modules[$module]), 404);
        return view('leap::layouts.app', ['currentModule' => $modules[$module]]);
    }
}
