<?php

namespace NickDeKruijk\Leap;

class Leap
{
    /**
     * Name of the binding in the IoC container
     *
     * @return string
     */
    public static function modules()
    {
        // dd(svg('far-images'));
        $modules = [
            'dashboard' => (object) [
                'slug' => '/',
                'isActive' => true,
                'icon' => svg('fas-gauge-high', 'nav-icon'),
                'title' => 'Dashboard',
            ],
            'media' => (object) [
                'slug' => 'media',
                'isActive' => false,
                'icon' => svg('far-images', 'nav-icon'),
                'title' => 'Media',
            ],
            'users' => (object) [
                'slug' => 'users',
                'isActive' => false,
                'icon' => svg('fas-users', 'nav-icon'),
                'title' => 'Users',
            ],
        ];
        return $modules;
    }
}
