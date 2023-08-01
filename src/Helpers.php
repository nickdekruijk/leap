<?php

namespace NickDeKruijk\Leap;

use Illuminate\Support\Facades\Auth;

class Helpers
{
    /**
     * Get the user model instance from leap config
     *
     * @return User;
     */
    public static function userModel()
    {
        Auth::shouldUse(config('leap.guard'));
        $model = Auth::getProvider()->getModel();
        return new $model;
    }
}
