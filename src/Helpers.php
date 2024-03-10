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
        $model = Auth::getProvider()->getModel();
        return new $model;
    }
}
