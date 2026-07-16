<?php

namespace NickDeKruijk\Leap;

use Collator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Traits\CanLog;

class Leap
{
    use CanLog;

    /**
     * Return the request-scoped Leap context (active module, permissions, role).
     */
    public static function context(): LeapContext
    {
        return app(LeapContext::class);
    }

    /**
     * Return all modules the current user has access to
     */
    public static function modules(): Collection
    {
        return ModuleController::getModules();
    }

    /**
     * Sort an array with locale-sensitive collator
     *
     * @param  array  $array  The array to sort
     * @return bool true on success or false on failure
     */
    public static function sort(array &$array): bool
    {
        $coll = collator_create(app()->getLocale());

        return collator_sort($coll, $array);
    }

    /**
     * Sort an array by key with locale-sensitive collator
     *
     * @param  array  $array  The array to sort
     * @param  string  $key  The key to sort by
     * @param  bool  $desc  Sort in descending order
     * @return bool true on success or false on failure
     */
    public static function sortBy(array &$array, $key, $desc = false): bool
    {
        $coll = collator_create(app()->getLocale());

        return usort($array, function ($a, $b) use ($coll, $key, $desc) {
            return $desc ? collator_compare($coll, $b[$key], $a[$key]) : collator_compare($coll, $a[$key], $b[$key]);
        });
    }

    /**
     * Sort an array by basename with locale-sensitive collator
     *
     * @param  array  $array  The array to sort
     * @return bool true on success or false on failure
     */
    public static function basenamesort(array &$array): bool
    {
        $coll = collator_create(app()->getLocale());
        $coll->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);

        return usort($array, function ($a, $b) use ($coll) {
            return collator_compare($coll, basename($a), basename($b));
        });
    }

    /**
     * Sort an array by key with locale-sensitive collator
     *
     * @param  array  $array  The array to sort
     * @return bool true on success or false on failure
     */
    public static function ksort(array &$array): bool
    {
        $coll = collator_create(app()->getLocale());

        return uksort($array, function ($a, $b) use ($coll) {
            return collator_compare($coll, $a, $b);
        });
    }

    /**
     * The default (first) configured locale, or null when the site is monolingual
     * (leap.locales is null/empty). This is the locale served without a URL prefix.
     */
    public static function localeDefault(): ?string
    {
        $locales = config('leap.locales');

        return $locales ? array_key_first($locales) : null;
    }

    /**
     * The URL prefix for the given locale (defaults to the active locale): an empty
     * string for the default/only locale, "/xx" otherwise. Shared by the frontend
     * template's routing, language switcher, sitemap and the leapLocalized() macro
     * so every locale-aware URL is built from one rule.
     */
    public static function localePrefix(?string $locale = null): string
    {
        if (! config('leap.locales')) {
            return '';
        }

        $locale ??= app()->getLocale();

        return $locale === self::localeDefault() ? '' : '/'.$locale;
    }

    /**
     * Detect a leading locale segment and apply it with app()->setLocale().
     *
     * No-op unless leap.locales defines locales. The default locale stays
     * unprefixed, so only a non-default, known locale segment is consumed
     * (shifted off $segments by reference). This is the segment-based counterpart
     * to the SetLeapLocale middleware (which works on a route prefix parameter);
     * both read the same leap.locales source so their locale rules never diverge.
     *
     * With no locale segment the default locale is applied explicitly rather than
     * left at whatever APP_LOCALE happens to be. Which locale is unprefixed is
     * declared by leap.locales, in config/leap.php and so in version control;
     * APP_LOCALE lives in .env, which is not, and differs per environment. Left
     * implicit, APP_LOCALE=en on a site whose first locale is nl rendered / in
     * English while every URL rule still treated / as the Dutch page -- English on
     * both / and /en, Dutch on nothing, and a language switcher pointing at neither.
     * The routing decision belongs to the file that is deployed with the code.
     *
     * @param  array<int, string>  $segments  URL path segments; a matched locale is removed
     */
    public static function detectLocale(array &$segments): void
    {
        $locales = config('leap.locales');
        if (! $locales) {
            return;
        }

        $default = self::localeDefault();
        if (isset($segments[0]) && $segments[0] !== '' && $segments[0] !== $default && array_key_exists($segments[0], $locales)) {
            app()->setLocale(array_shift($segments));

            return;
        }

        app()->setLocale($default);
    }

    /**
     * Check if the user has permission for the ability, if not raise HtmlException and Log
     *
     * @param  string  $ability  The permission to check
     * @param  int  $code  Http response code to throw on gate failure (default 403: Unauthorized)
     * @return void
     */
    public static function validatePermission(string $ability, int $code = 403)
    {
        if (Gate::denies('leap::'.$ability)) {
            self::log('unauthorized', ['ability' => $ability, 'code' => $code, 'requestUri' => request()->getRequestUri()]);
            abort($code);
        }
    }

    /**
     * Get the user model instance from leap config
     *
     * @return Authenticatable;
     */
    public static function userModel(): Authenticatable
    {
        /** @disregard P1013 Prevent intelephense warning "Undefined method 'getModel'" */
        $model = Auth::getProvider()->getModel();

        return new $model;
    }

    /**
     * Determine which two factor method, if any, is active for the given
     * user. Returns 'totp', 'email', 'passkey' or null.
     */
    public static function twoFactorMethod(?Authenticatable $user = null): ?string
    {
        $user ??= Auth::guard(config('leap.guard'))->user();

        if (! $user) {
            return null;
        }

        if (method_exists($user, 'hasEnabledTwoFactorAuthentication') && $user->hasEnabledTwoFactorAuthentication()) {
            return 'totp';
        }

        if (! empty($user->two_factor_email_confirmed_at)) {
            return 'email';
        }

        if (
            config('leap.auth_passkeys.satisfies_2fa_requirement')
            && method_exists($user, 'passkeys')
            && $user->passkeys()->exists()
        ) {
            return 'passkey';
        }

        return null;
    }

    /**
     * Determine whether the authenticated user still needs to pass the two
     * factor authentication challenge before accessing the panel.
     */
    public static function mustValidateTwoFactor(): bool
    {
        $user = Auth::guard(config('leap.guard'))->user();

        return config('leap.auth_2fa.enabled')
            && $user
            && self::twoFactorMethod($user) !== null
            && ! session('leap.auth_2fa.validated');
    }

    /**
     * Determine whether the authenticated user must enroll a two factor
     * method before accessing anything besides their own profile.
     */
    public static function mustEnrollTwoFactor(): bool
    {
        $user = Auth::guard(config('leap.guard'))->user();

        if (! config('leap.auth_2fa.required') || ! $user) {
            return false;
        }

        return self::twoFactorMethod($user) === null;
    }

    /**
     * Generate the permissions section for the role management
     */
    public static function generatePermissionsSection(): Attribute
    {
        // First add the all modules section with full access switch
        $sections[] = Section::make('all_modules')->withoutView()->label(__('leap::auth.all_modules'))->attributes(
            Attribute::make('all_permissions')->switch()->label(__('leap::auth.full_access')),
        );

        // Then add the sections for each module with their permissions as switches
        foreach (ModuleController::getAllModules() as $module) {
            $attributes = [];
            foreach ($module->getDefaultPermissions() as $permission => $default) {
                $attributes[] = Attribute::make($permission)->switch()->default($default)->label(__('leap::auth.'.$permission));
            }
            $sections[] = Section::make($module::class)->withoutView()->label($module->getTitle())->attributes(...$attributes);
        }

        // Return the sections as an Attribute
        return Attribute::make('permissions')->label(__('leap::auth.permissions'))->sections(...$sections);
    }

    public static function htmlTitle(): string
    {
        $title = config('leap.title');

        if ($module = static::context()->module()) {
            $moduleTitle = (new $module)->getTitle();
            $title = str_replace('{module}', $moduleTitle, $title);
        } else {
            $title = str_replace('{module} - ', '', $title);
        }

        return $title;
    }
}
