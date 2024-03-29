<?php

namespace NickDeKruijk\Leap;

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
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
        $this->loadJSONTranslationsFrom(__DIR__ . '/../resources/lang');

        $this->publishes([
            __DIR__ . '/../config/leap.php' => config_path('leap.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'leap');

        // Register all leap livewire components.
        foreach (glob(__DIR__ . '/Livewire/*.php') as $file) {
            Livewire::component('leap.' . strtolower(basename($file, '.php')), 'NickDeKruijk\Leap\Livewire\\' . basename($file, '.php'));
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
                UserCommand::class,
            ]);
        }
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
