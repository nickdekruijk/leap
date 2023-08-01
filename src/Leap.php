<?php

namespace NickDeKruijk\Leap;

use NickDeKruijk\Leap\Controllers\ModuleController;

class Leap
{
    /**
     * Name of the binding in the IoC container
     *
     * @return string
     */
    public static function modules()
    {
        return ModuleController::getModules();
    }
}
