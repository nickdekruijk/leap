<?php

namespace NickDeKruijk\Leap;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Events\PasskeyVerified;
use Laravel\Passkeys\Passkeys;
use Livewire\Livewire;
use NickDeKruijk\Leap\Classes\ImageResizer;
use NickDeKruijk\Leap\Classes\NotFoundLog;
use NickDeKruijk\Leap\Commands\ImageCommand;
use NickDeKruijk\Leap\Commands\MediaCommand;
use NickDeKruijk\Leap\Commands\ModuleCommand;
use NickDeKruijk\Leap\Commands\RobotsCommand;
use NickDeKruijk\Leap\Commands\UserCommand;
use NickDeKruijk\Leap\Jobs\GenerateImageDerivatives;
use NickDeKruijk\Leap\Middleware\Auth2FA;
use NickDeKruijk\Leap\Middleware\LeapAuth;
use NickDeKruijk\Leap\Middleware\RequireRole;
use NickDeKruijk\Leap\Middleware\RequireTwoFactorEnrollment;
use NickDeKruijk\Leap\Middleware\SetLeapLocale;
use NickDeKruijk\Leap\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // The consent banner and cookie table ship with the package rather than as
        // template stubs, so a fix reaches every site through composer instead of
        // leaving each one on its own frozen copy of something that has to hold up
        // legally. Publish them only to replace the markup outright — styling is meant
        // to happen from the project's own stylesheet, since the CSS carries structure
        // and reads the template's design tokens for everything else.
        // robots.txt is published for the same reason and with the opposite intent: the
        // config covers the rules a site is likely to want, and publishing is for the
        // one that wants to write the file itself.
        $this->publishes([
            __DIR__.'/../resources/views/consent-banner.blade.php' => resource_path('views/vendor/leap/consent-banner.blade.php'),
            __DIR__.'/../resources/views/cookie-table.blade.php' => resource_path('views/vendor/leap/cookie-table.blade.php'),
            __DIR__.'/../resources/views/robots.blade.php' => resource_path('views/vendor/leap/robots.blade.php'),
        ], 'leap-views');

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

        $this->registerNotFoundLog();

        // At the root of the site, and before the frontend's own routes: a package
        // provider boots ahead of the one that loads routes/web.php, so a catch-all
        // there cannot swallow /robots.txt, which is matched first. A route on that
        // exact address is another matter: a route collection is keyed on method plus
        // URI, so the project's replaces this one outright. leap:robots reports that.
        if (config('leap.robots.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/robots.php');
        }

        // Public, unauthenticated, and outside the panel prefix: this is the
        // fallback that generates a resized copy the web server just failed to
        // find. Only registered when the feature is on, so a site still running
        // nickdekruijk/imageresize cannot end up with two packages claiming
        // overlapping paths.
        if (config('leap.images.enabled')) {
            $this->registerImagesDisk();
            $this->loadRoutesFrom(__DIR__.'/../routes/images.php');

            // Nothing overwrites a resized copy, so an image that goes away or
            // changes leaves its own behind. Both are known exactly here — the
            // path and the hash it had — so they can be taken along at once
            // rather than swept up later by leap:images --prune, which is for
            // what happens out of leap's sight.
            Media::deleted(function (Media $media): void {
                ImageResizer::forget($media->file_name, $media->sha256);
            });

            Media::updated(function (Media $media): void {
                foreach (['file_name', 'sha256'] as $attribute) {
                    if ($media->wasChanged($attribute)) {
                        // Whatever it was before this save: a renamed file and a
                        // replaced one both orphan the copies of the old one.
                        ImageResizer::forget($media->getOriginal('file_name'), $media->getOriginal('sha256'));

                        return;
                    }
                }
            });

            if (config('leap.images.eager')) {
                // Every path leap writes a file through ends in a saved Media
                // row, so this one listener covers uploads, crops, AI images and
                // anything an application adds later.
                Media::saved(function (Media $media): void {
                    if ($media->isBitmap() && $media->sha256) {
                        GenerateImageDerivatives::dispatch($media->id, $media->sha256);
                    }
                });
            }
        }

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

            // laravel/fortify (^1.37) bundles its own passkeys integration
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
                ImageCommand::class,
                MediaCommand::class,
                ModuleCommand::class,
                RobotsCommand::class,
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

        $this->registerLocalizedRouteMacro();
    }

    /**
     * Note a page that was asked for and is not there, when the site asks for that.
     *
     * Hung off render() rather than report(), and the difference is not a preference:
     * Symfony's HttpException is on Laravel's internal do-not-report list, so a report
     * callback is never handed a 404 at all. Taking it off that list to reach one would
     * hand every 403 and every abort() to everything else that reports, Sentry included.
     * The callback returns null, so the error page renders as it always did.
     *
     * Registered from here because this package is the one that is installed in
     * production. The frontend template is a dev dependency — its service provider is
     * absent on the server, which is the only place a 404 log is worth anything.
     *
     * callAfterResolving, so nothing forces the exception handler into existence early on
     * a console boot that will never need it — and the callback costs nothing until
     * something actually throws.
     *
     * Registered whether or not the feature is on, with NotFoundLog::record() asking that
     * question when it is called. Reading the config here instead would decide it once,
     * at boot, before a test or a runtime override has had a chance to say otherwise.
     */
    protected function registerNotFoundLog(): void
    {
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! method_exists($handler, 'renderable')) {
                return;
            }

            $handler->renderable(function (NotFoundHttpException $e, Request $request): void {
                NotFoundLog::record($request);
            });
        });
    }

    /**
     * Register the Route::leapLocalized() macro for frontend routes whose URL
     * segment differs per locale (e.g. 'diensten' in nl, 'services' in en) and
     * that live outside the page tree.
     *
     * Usage in a project's routes/web.php:
     *
     *     Route::leapLocalized(['nl' => 'diensten', 'en' => 'services'], function (string $locale, string $segment) {
     *         Route::get($segment.'/{slug}', [PageController::class, 'service'])->name('service.'.$locale);
     *     });
     *
     * One route group is registered per configured locale that has a segment.
     * The default locale is served unprefixed; every other locale is prefixed
     * with "/xx" (Leap::localePrefix()). The request locale is set per group by
     * SetLeapLocale middleware -- never here at registration time, which would
     * be too early for a per-request locale. When the site is monolingual
     * (leap.locales is null) a single unprefixed group is registered using the
     * current app locale's segment (falling back to the first).
     */
    protected function registerLocalizedRouteMacro(): void
    {
        Route::macro('leapLocalized', function (array $segments, Closure $callback): void {
            $locales = config('leap.locales');

            if (! $locales) {
                $locale = app()->getLocale();
                $callback($locale, $segments[$locale] ?? reset($segments));

                return;
            }

            foreach ($locales as $locale => $name) {
                $segment = $segments[$locale] ?? null;
                if ($segment === null) {
                    continue;
                }

                Route::prefix(Leap::localePrefix($locale))
                    ->middleware(SetLeapLocale::class.':'.$locale)
                    ->group(fn () => $callback($locale, $segment));
            }
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

        return (($modulePermissions[$ability] ?? false) === true)
            || (($modulePermissions['all_permissions'] ?? false) === true);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeLeapConfigFrom(__DIR__.'/../config/leap.php', 'leap');

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

    /**
     * Define the disk resized images are written to, so a project does not have
     * to add one to config/filesystems.php to use the feature.
     *
     * Rooted in storage/ and reached through a symlink from public/, the way
     * Laravel's own public disk is. The whole point of the feature is that the
     * web server serves a generated copy directly on every request after the
     * first, and a symlink is served exactly like a directory. Writing into
     * public/ itself would do that too, until the first deploy: a release-based
     * deploy (Forge, Envoyer) builds a new public/ every time and the cache of
     * every size of every image on the site is gone with the old release,
     * regenerated one visitor at a time. storage/ is shared between releases,
     * so what is there survives.
     *
     * In storage/app/leap-images rather than storage/app/public, which is where
     * a first attempt at this put it: that directory already has a link of its
     * own, so every copy would be reachable at /storage/img/... as well as at
     * /img/..., and a search engine would find the same picture at two
     * addresses. Its own directory has exactly one way in.
     *
     * The link is registered with `php artisan storage:link` rather than made
     * here: it belongs to a deploy, not to a request, and a web request has no
     * business writing into public/. A missing link is not fatal, only slow --
     * every request then misses on disk and is answered by ImageController out
     * of storage, which is what already happens for the first request of any
     * URL. `leap:images` says so when it sees it, because nothing else would.
     *
     * Set from boot() rather than register(): a disk is only ever read when
     * Storage::disk() is called, and by boot() the configuration is settled
     * whichever way the application was assembled.
     *
     * A disk the project defines itself under the same name always wins — that
     * is how you point this at s3 (together with leap.images.eager, since
     * nothing can fall back to PHP for a URL the web server never sees).
     */
    protected function registerImagesDisk(): void
    {
        $disk = config('leap.images.disk');

        if (! $disk) {
            return;
        }

        $route = trim((string) config('leap.images.route'), '/');

        if (! config('filesystems.disks.'.$disk)) {
            config(['filesystems.disks.'.$disk => [
                'driver' => 'local',
                'root' => static::imagesRoot(),
                'url' => '/'.$route,
                'visibility' => 'public',
                // Never reachable through Laravel's own /storage route: a
                // resized copy is served from public/<route> over the link,
                // and one URL per copy is the whole point.
                'serve' => false,
                'throw' => false,
            ]]);
        }

        $this->registerImagesLink($disk, $route);
    }

    /**
     * Where the copies live when leap defines the disk itself.
     *
     * Static so the command that reports on the link can ask for it without
     * repeating the path, which is the sort of thing that goes wrong once and
     * then reads as a missing link forever.
     */
    public static function imagesRoot(): string
    {
        return storage_path('app/leap-images');
    }

    /**
     * Tell `php artisan storage:link` about the link that makes public/<route>
     * the copies, so nothing has to be added to config/filesystems.php.
     *
     * Registered for the project's own disk as well, not only for leap's:
     * defining that disk is usually about a setting on it, not about giving up
     * the link, and without one the site quietly serves every resized image
     * through PHP. The two cases where there is nothing to link are left alone:
     * a driver with no directory behind it (s3), and a disk already rooted
     * inside public/, which the web server reaches where it stands.
     *
     * A path the project already lists is never touched. Saying where the link
     * goes is exactly how a project overrides this.
     */
    protected function registerImagesLink(string $disk, string $route): void
    {
        $config = config('filesystems.disks.'.$disk);
        $root = $config['root'] ?? null;

        if (($config['driver'] ?? null) !== 'local' || ! $root || str_starts_with($root, public_path())) {
            return;
        }

        // ?: rather than a default, because a project is free to have set this
        // to null and config() would hand that straight back.
        $links = config('filesystems.links') ?: [];

        if (! isset($links[public_path($route)])) {
            $links[public_path($route)] = $root;
            config(['filesystems.links' => $links]);
        }
    }

    /**
     * Merge the package config into the published one, nested keys included.
     *
     * Laravel's own mergeConfigFrom is a single array_merge, so a published
     * config.leap replaces whole top-level sections: a project that published
     * config/leap.php before a release added leap.ai.show_costs reads null for
     * it, not the true this package ships. Every key added below the top level
     * would otherwise silently arrive turned off in every existing install.
     */
    protected function mergeLeapConfigFrom(string $path, string $key): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');

        $config->set($key, $this->mergeLeapConfig(require $path, $config->get($key, [])));
    }

    /**
     * The published value wins wherever it says anything; the package fills in
     * what it is silent about.
     *
     * Only maps are merged. A list is a complete answer — dropping a module
     * from default_modules or an extension from filemanager.allowed_extensions
     * means exactly that, and merging by index would hand the removed entries
     * straight back. So a list replaces its counterpart whole, as does any
     * value whose shape differs between the two (a map published over a list,
     * or the other way round).
     *
     * @param  array<mixed>  $package
     * @param  array<mixed>  $published
     * @return array<mixed>
     */
    protected function mergeLeapConfig(array $package, array $published): array
    {
        foreach ($published as $key => $value) {
            $package[$key] = $this->isMap($value) && $this->isMap($package[$key] ?? null)
                ? $this->mergeLeapConfig($package[$key], $value)
                : $value;
        }

        return $package;
    }

    /**
     * Whether a value is an array keyed by name rather than by position. An
     * empty array counts as a list: 'presets' => [] is an editor saying there
     * are none, which a merge must not undo.
     */
    protected function isMap(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
    }
}
