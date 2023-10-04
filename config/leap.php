<?php

use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Profile;

return [

    /*
    |--------------------------------------------------------------------------
    | app_modules
    |--------------------------------------------------------------------------
    |
    | Leap modules are loaded from a directory inside the app directory of the
    | Laravel project. The default 'Leap' means app_path('Leap'), which 
    | resolves to 'app/Leap', will be used to search for modules and the class
    | namespace should be App\Leap. Each class in this directory should extend 
    | the NickDeKruijk\Leap\Module class. 
    |
    */
    'app_modules' => 'Leap',

    /*
    |--------------------------------------------------------------------------
    | auth_2fa
    |--------------------------------------------------------------------------
    |
    | Enable two factor authentication. This can be done by mail. 
    | The mail method will send a code to the users email address.
    | In a future release TOTP (Google Authenticator) will be added.
    |
    */
    'auth_2fa' => [
        'method' => null, // 'mail', null
        'mail' => [
            'subject' => 'Your 2FA code', // Will be localized with trans()
            'view' => 'leap::emails.2fa',
            'from' => config('mail.from'),
            'code' => [
                'length' => 6,
                'charlist' => '0-9', // Examples: '0-9a-zA-Z', 'A-Z' or '0-9'
                'case_sensitive' => false,
                'expires' => 15, // Minutes
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | auth_routes
    |--------------------------------------------------------------------------
    |
    | Register authentication routes for login and logout. Disable these if you
    | want to use Laravels Auth::routes() or customize it yourself.
    |
    */
    'auth_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | credentials
    |--------------------------------------------------------------------------
    |
    | The credentials to use when logging in a user, e.g. ['email', 'password']
    |
    */
    'credentials' => ['email', 'password'],

    /*
    |--------------------------------------------------------------------------
    | default_modules
    |--------------------------------------------------------------------------
    |
    | The default modules to show in the navigation. You can add your own
    | modules in the app/Leap directory. The default modules are the Dashboard
    | and Profile modules.
    |
    */
    'default_modules' => [
        new Dashboard(['priority' => 0]),
        Profile::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | guard
    |--------------------------------------------------------------------------
    |
    | The guard to use when trying to login a user, e.g. 'web' that uses the
    | default User model. To seperate application users from leap users define
    | a new guard in the config/auth.php file.
    |
    */
    'guard' => Auth::getDefaultDriver(),

    /*
    |--------------------------------------------------------------------------
    | migrations
    |--------------------------------------------------------------------------
    |
    | Enable migrations. This will create the leap_roles table and the
    | leap_role_user pivot table. The User model should have a belongsToMany
    | relationship with the Role model.
    |
    | Also a default 'Admin' role will be created with all permissions and 
    | assigned to the first user in the users table with id 1.
    |
    */
    'migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | organizations
    |--------------------------------------------------------------------------
    |
    | Enable organizations support. Your application should have a valid
    | organization model with a belongsToMany relationship with the User model.
    | See organization_model configuration below.
    |
    */
    'organizations' => false,

    /*
    |--------------------------------------------------------------------------
    | organization_model
    |--------------------------------------------------------------------------
    |
    | The model to use for organizations, e.g. App\Models\Organization
    | This model should have a belongsToMany relationship with the User model.
    |
    */
    'organization_model' => 'App\Models\Organization',

    /*
    |--------------------------------------------------------------------------
    | route_prefix
    |--------------------------------------------------------------------------
    |
    | The prefix added to the routes added by leap. 
    | For example, if the default prefix is 'leap-admin', the routes will be 
    | domain.com/leap-admin and domain.com/leap-admin/componentname
    | You can change this to for example 'app' to have routes like 
    | domain.com/app and domain.com/app/componentname or just '/' to have routes 
    | like domain.com/ and domain.com/componentname
    |
    */
    'route_prefix' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | table_prefix
    |--------------------------------------------------------------------------
    |
    | The package inclused migrations to create tables. The created tables name
    | will use this prefix, e.g. 'leap_' for leap_roles and leap_role_user.
    |
    */
    'table_prefix' => 'leap_',

    /*
    |--------------------------------------------------------------------------
    | theme
    |--------------------------------------------------------------------------
    |
    | The CSS theme file to use. For example 'admin' or 'saas'.
    |
    */
    'theme' => 'admin',

];
