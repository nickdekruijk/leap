<?php

namespace NickDeKruijk\Leap;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Events\PasskeyVerified;
use Laravel\Passkeys\Passkeys;
use Livewire\Livewire;
use NickDeKruijk\Leap\Commands\ModuleCommand;
use NickDeKruijk\Leap\Commands\TemplateCommand;
use NickDeKruijk\Leap\Commands\UserCommand;
use NickDeKruijk\Leap\Middleware\Auth2FA;
use NickDeKruijk\Leap\Middleware\LeapAuth;
use NickDeKruijk\Leap\Middleware\RequireRole;
use NickDeKruijk\Leap\Middleware\RequireTwoFactorEnrollment;

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
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'leap');

        $this->publishes([
            __DIR__.'/../config/leap.php' => config_path('leap.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'leap');

        // Register all leap livewire components.
        foreach (glob(__DIR__.'/Livewire/*.php') as $file) {
            // convert PascalCase to kebab-case
            $kebabCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', basename($file, '.php')));
            Livewire::component('leap.'.$kebabCase, 'NickDeKruijk\Leap\Livewire\\'.basename($file, '.php'));
        }

        // Register all components in app/Leap directory
        foreach (glob(app_path(config('leap.app_modules')).'/*.php') as $file) {
            Livewire::component('leap.app.'.strtolower(basename($file, '.php')), 'App\Leap\\'.basename($file, '.php'));
        }

        // Leap middleware should be persistent for all livewire requests
        Livewire::addPersistentMiddleware([
            Auth2FA::class,
            LeapAuth::class,
            RequireRole::class,
            RequireTwoFactorEnrollment::class,
        ]);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if (config('leap.migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/../migrations');
        }

        if (config('leap.auth_passkeys.enabled')) {
            // Configure laravel/passkeys to log in on the same guard and user
            // model leap uses. Read here (boot, not register) so this sees
            // the final auth config after every provider has registered, and
            // wins even if Fortify's own passkeys wiring (which always runs
            // in its register(), regardless of provider order) touched these
            // same keys first.
            config(['passkeys.guard' => config('leap.guard')]);
            // Leap has no password.confirm route/flow, so drop the package's
            // default 'password.confirm' management middleware: passkey
            // management routes are already gated by leap's own auth stack
            // (LeapAuth/RequireRole/Auth2FA) plus Leap::validatePermission('update')
            // in Profile, the same protection level as enabling/disabling
            // TOTP two factor.
            config(['passkeys.management_middleware' => []]);
            $authProvider = config('auth.guards.'.config('leap.guard').'.provider');
            Passkeys::useUserModel(config('auth.providers.'.$authProvider.'.model'));

            // When a passkey counts as a 2FA method (leap.auth_passkeys.satisfies_2fa_requirement),
            // any successful WebAuthn verification -- whether from logging in with a passkey or
            // from confirming one on the two factor challenge page -- validates the session the
            // same way entering a TOTP/email code does.
            Event::listen(PasskeyVerified::class, function () {
                if (config('leap.auth_passkeys.satisfies_2fa_requirement')) {
                    session(['leap.auth_2fa.validated' => true]);
                }
            });

            // laravel/fortify (^1.19) bundles its own passkeys integration
            // and unconditionally calls Passkeys::ignoreRoutes() as soon as
            // it registers, whether or not Fortify's passkeys feature is
            // enabled. Combined with Fortify::ignoreRoutes() above (needed
            // to keep leap's own Livewire login), the package's routes would
            // never get registered by anyone. Load them directly instead of
            // relying on PasskeysServiceProvider's own auto-registration.
            $this->loadRoutesFrom(
                InstalledVersions::getInstallPath('laravel/passkeys').'/routes/routes.php'
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleCommand::class,
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
     * @return bool
     */
    public function can(string $ability, ?Module $module = null)
    {
        $modulePermissions = Leap::context()->permissionsFor($module ? $module::class : null);

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
        $this->mergeConfigFrom(__DIR__.'/../config/leap.php', 'leap');

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

        // Request-scoped store for the active module, permissions and role.
        // Scoped so it is flushed between requests / Livewire updates and never
        // leaks into queued jobs the way Laravel's Context does.
        $this->app->scoped(LeapContext::class);
    }
}
