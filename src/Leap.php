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
