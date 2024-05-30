<?php

use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Profile;
use NickDeKruijk\Leap\Navigation\Divider;
use NickDeKruijk\Leap\Navigation\Logout;
use NickDeKruijk\Leap\Navigation\Organizations;

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
    | modules in the app/Leap directory (see app_modules configuration above).
    | A module must extend the Leap/Module or Leap/Resource class or use the 
    | NavigationItem trait.   
    |
    */
    'default_modules' => [
        Dashboard::class,
        Divider::class,
        Organizations::class,
        Profile::class,
        Logout::class,
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
    | permission_priority
    |--------------------------------------------------------------------------
    |
    | The priority of permissions when organizations are enabled. When a user
    | has module permissions from both a global and organization role this
    | setting determines which role permission to use. Possible values are
    | 'global' and 'organization'. So you can either overrule the global role
    | permissions with organization role permissions or the other way around.
    |
    */
    'permission_priority' => 'organization',

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
    | By default an organization will be refered to by a slug and the name will
    | be shown in the navigation. You can overrule this by adding a $leap_slug,
    | $leap_navigation_label and $leap_navigation_order attribute to the model.
    | For example (with the default values):
    | class Organization extends Model
    | {
    |     public $leap_slug = 'slug';
    |     public $leap_navigation_label = 'name';
    |     public $leap_navigation_order = 'name';
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
    | css
    |--------------------------------------------------------------------------
    |
    | An array of css/sass files to include in the head of the app layout.
    | These will be compiled with ScssPhp into a single css file and cached.
    | Be aware that ScssPhp does not support all sass features like @use.
    | The package resource/css directory is added to @import paths. Be careful
    | with @import bacause those files are not watched for cache changes.
    | If path is ommited the file in the resources/css directory is used. 
    | 
    */
    'css' => [
        'minireset.scss',
        'colors.scss',
        'dashboard.scss',
        'default.scss',
        'editor.scss',
        'forms.scss',
        'index.scss',
        'login.scss',
        'logo.scss',
        'nav.scss',
        'toasts.scss',
        // base_path('resources/css/custom.scss'),
    ],

    /*
    |--------------------------------------------------------------------------
    | login_image
    |--------------------------------------------------------------------------
    | Image to show on the login screen. By default random from picsum.photos.
    | Default image viewport is 380x332 pixels and zooms to 1.5 magnification 
    | and times two for retina screens. So 1140x996 pixels is a good size.
    */
    'login_image' => 'https://picsum.photos/1140/996',

];
