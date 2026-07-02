<?php

namespace NickDeKruijk\Leap;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
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
            // convert PascalCase to kebab-case
            $kebabCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', basename($file, '.php')));
            Livewire::component('leap.' . $kebabCase, 'NickDeKruijk\Leap\Livewire\\' . basename($file, '.php'));
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

        // Configure Laravel Fortify for per-user TOTP two factor authentication.
        // Leap drives its own routes and Livewire UI, so Fortify's own routes are
        // disabled and only its two factor primitives are used. Registration and
        // email verification are intentionally left disabled. This runs in
        // register() so it takes effect before any provider (including Fortify)
        // boots and registers its routes.
        Fortify::ignoreRoutes();
        config(['fortify.guard' => config('leap.guard')]);
        config(['fortify.features' => config('leap.auth_2fa.enabled') ? [
            // 'confirm' must stay true: the Profile UI always requires a
            // valid code before showing 2FA as enabled, so leaving this
            // false would only desync the login gate from that (risking
            // lockout on an unconfirmed secret) with no upside.
            Features::twoFactorAuthentication([
                'confirm' => true,
                'confirmPassword' => false,
            ]),
        ] : []]);

        // Register the main class to use with the facade
        $this->app->singleton('leap', function () {
            return new Leap;
        });
    }
}
