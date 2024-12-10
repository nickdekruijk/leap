<?php

namespace NickDeKruijk\Leap;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use NickDeKruijk\Leap\Controllers\ModuleController;

class Leap
{
    /**
     * Return all modules the current user has access to
     *
     * @return string
     */
    public static function modules()
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
     * Get the user model instance from leap config
     *
     * @return Authenticatable;
     */
    public static function userModel(): Authenticatable
    {
        $model = Auth::getProvider()->getModel();
        return new $model;
    }
}
