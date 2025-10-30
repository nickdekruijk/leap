<?php

namespace NickDeKruijk\Leap;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use NickDeKruijk\Leap\Commands\TemplateCommand;
use NickDeKruijk\Leap\Commands\UserCommand;
use NickDeKruijk\Leap\Middleware\Auth2FA;
use NickDeKruijk\Leap\Middleware\LeapAuth;
use NickDeKruijk\Leap\Middleware\RequireRole;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Load the translations JSON files.
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'leap');

        $this->publishes([
            __DIR__ . '/../config/leap.php' => config_path('leap.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'leap');

        // Register all leap livewire components.
        foreach (glob(__DIR__ . '/Livewire/*.php') as $file) {
            Livewire::component('leap.' . strtolower(basename($file, '.php')), 'NickDeKruijk\Leap\Livewire\\' . basename($file, '.php'));
        }

        // Register all components in app/Leap directory
        foreach (glob(app_path(config('leap.app_modules')) . '/*.php') as $file) {
            Livewire::component('leap.app.' . strtolower(basename($file, '.php')), 'App\Leap\\' . basename($file, '.php'));
        }

        // Leap middleware should be persistent for all livewire requests
        Livewire::addPersistentMiddleware([
            Auth2FA::class,
            LeapAuth::class,
            RequireRole::class,
        ]);

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if (config('leap.migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                TemplateCommand::class,
                UserCommand::class,
            ]);
        }

        Gate::define('leap::create', function ($user, ?Module $module = null) {
            return $this->can('create', $module);
        });
        Gate::define('leap::read', function ($user, ?Module $module = null) {
            return $this->can('read', $module);
        });
        Gate::define('leap::update', function ($user, ?Module $module = null) {
            return $this->can('update', $module);
        });
        Gate::define('leap::delete', function ($user, ?Module $module = null) {
            return $this->can('delete', $module);
        });
    }

    /**
     * Check if user has permission for the module ability
     *
     * @param string $ability
     * @return boolean
     */
    public function can(string $ability, ?Module $module = null)
    {
        $modulePermissions = Context::getHidden('leap.permissions')[$module ? $module::class : Context::getHidden('leap.module')];
        return ($modulePermissions[$ability] ?? false === true)
            || ($modulePermissions['all_permissions'] ?? false === true);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/leap.php', 'leap');

        // Register the main class to use with the facade
        $this->app->singleton('leap', function () {
            return new Leap;
        });
    }
}
