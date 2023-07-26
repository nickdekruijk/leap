<?php

return [

    /*
    |--------------------------------------------------------------------------
    | auth_routes
    |--------------------------------------------------------------------------
    | Register authentication routes for login and logout. Disable these if you
    | want to use Laravels Auth::routes() or customize it yourself.
    */
    'auth_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | credentials
    |--------------------------------------------------------------------------
    | The credentials to use when logging in a user, e.g. ['email', 'password']
    */
    'credentials' => ['email', 'password'],

    /*
    |--------------------------------------------------------------------------
    | guard
    |--------------------------------------------------------------------------
    | The guard to use when trying to login a user, e.g. 'web' that uses the
    | default User model. To seperate application users from leap users define
    | a new guard in the config/auth.php file.
    */
    'guard' => Auth::getDefaultDriver(),

    /*
    |--------------------------------------------------------------------------
    | route_prefix
    |--------------------------------------------------------------------------
    | The prefix added to the routes added by leap. 
    | For example, if the default prefix is 'leap-admin', the routes will be 
    | domain.com/leap-admin and domain.com/leap-admin/componentname
    | You can change this to for example 'app' to have routes like 
    | domain.com/app and domain.com/app/componentname or just '/' to have routes 
    | like domain.com/ and domain.com/componentname
    */
    'route_prefix' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | theme
    |--------------------------------------------------------------------------
    | The CSS theme file to use. For example 'admin' or 'saas'.
    */
    'theme' => 'admin',

];
