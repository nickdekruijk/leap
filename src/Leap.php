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
     * Return all modules the current user has access to
     *
     * @return Collection
     */
    public static function modules(): Collection
    {
        return ModuleController::getModules();
    }

    /**
     * Sort an array with locale-sensitive collator
     *
     * @param array $array The array to sort
     * @return boolean true on success or false on failure
     */
    public static function sort(array &$array): bool
    {
        $coll = collator_create(app()->getLocale());
        return collator_sort($coll, $array);
    }

    /**
     * Sort an array by key with locale-sensitive collator
     *
     * @param array $array The array to sort
     * @param string $key The key to sort by
     * @param boolean $desc Sort in descending order
     * @return boolean true on success or false on failure
     */
    public static function sortBy(array &$array, $key, $desc = false): bool
    {
        $coll = collator_create(app()->getLocale());
        return usort($array, function ($a, $b) use ($coll, $key, $desc) {
            return  $desc ? collator_compare($coll, $b[$key], $a[$key]) : collator_compare($coll, $a[$key], $b[$key]);
        });
    }

    /**
     * Sort an array by basename with locale-sensitive collator
     *
     * @param array $array The array to sort
     * @return boolean true on success or false on failure
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
     * @param array $array The array to sort
     * @return boolean true on success or false on failure
     */
    public static function ksort(array &$array): bool
    {
        $coll = collator_create(app()->getLocale());
        return uksort($array, function ($a, $b) use ($coll) {
            return collator_compare($coll, $a, $b);
        });
    }

    /**
     * Check if the user has permission for the ability, if not raise HtmlException and Log
     *
     * @param string $ability The permission to check
     * @param integer $code Http response code to throw on gate failure (default 403: Unauthorized)
     * @return void
     */
    public static function validatePermission(string $ability, int $code = 403)
    {
        if (Gate::denies('leap::' . $ability)) {
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
     * Generate the permissions section for the role management
     *
     * @return Attribute
     */
    public static function generatePermissionsSection(): Attribute
    {
        // First add the all modules section with full access switch
        $sections[] = Section::make('all_modules')->withoutView()->label(__('leap::auth.all_modules'))->attributes(
            Attribute::make('all_permissions')->switch()->label(__('leap::auth.full_access')),
        );

        // Then add the sections for each module with their permissions as switches
        foreach (ModuleController::getAllModules() as $module) {
            if (config('leap.organizations') || $module::class !== 'NickDeKruijk\Leap\Navigation\Organizations') {
                $attributes = [];
                foreach ($module->getDefaultPermissions() as $permission => $default) {
                    $attributes[] = Attribute::make($permission)->switch()->default($default)->label(__('leap::auth.' . $permission));
                }
                $sections[] = Section::make($module::class)->withoutView()->label($module->getTitle())->attributes(...$attributes);
            }
        }

        // Return the sections as an Attribute
        return Attribute::make('permissions')->label(__('leap::auth.permissions'))->sections(...$sections);
    }
}
