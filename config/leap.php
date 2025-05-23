<?php

use NickDeKruijk\Leap\Controllers\LogoutController;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Livewire\Login;
use NickDeKruijk\Leap\Livewire\Profile;
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
        Organizations::class,
        FileManager::class,
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
    'guard' => 'web',

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
        'filemanager.scss',
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
    |
    */
    'login_image' => 'https://picsum.photos/1140/996',

    /*
    |--------------------------------------------------------------------------
    | logging
    |--------------------------------------------------------------------------
    | Options for logging user actions. By default all actions are logged from
    | all leap modules. The 'skip_actions' and 'skip_modules' option can be 
    | used to exclude certain actions and modules from being logged.
    |
    */
    'logging' => [
        'enabled' => true, // Enable or disable all logging
        'skip_actions' => [
            // 'login',
            // 'login-failed',
            // 'login-throttle',
            // 'logout',
            // 'create',
            'read',
            // 'update',
            // 'delete',
        ],
        'skip_modules' => [
            // Login::class,
            // LogoutController::class,
            // Dashboard::class,
        ],
        'ip_address' => true, // Log IP address with each log entry
        'ip_address_anonymized' => false, // Anonymize IP address by replacing last part with .xxx (or :xxxx:xxxx for IPv6)
        'user_agent' => true, // Store user agent with each log entry
    ],

    /*
    |--------------------------------------------------------------------------
    | filemanager
    |--------------------------------------------------------------------------
    | Configuration for the built in file manager
    |
    */
    'filemanager' => [
        'disk' => 'public', // Must refer to a disk defined in config/filesystems.php, e.g. 'local' or 'public'
        'upload_max_filesize' => '128G', // Maximum size of an uploaded file in bytes, still limited by php.ini upload_max_filesize and post_max_size
        'allowed_extensions' => ['png', 'jpg', 'jpeg', 'gif', 'svg', 'zip', 'pdf', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'pages', 'numbers', 'psd', 'ai', 'eps', 'mp4', 'mp3', 'mpg', 'm4a', 'ogg', 'sketch', 'json', 'rtf', 'md'],
    ],

    /*
    |--------------------------------------------------------------------------
    | tinymce
    |--------------------------------------------------------------------------
    | TinyMCE options like CDN and version to use.
    | cdn: By default the latest 7.x version.
    | options: This will be converted to json and added to tinymce.init().
    |          See https://www.tiny.cloud/docs/tinymce/7/ for options.
    |
    */
    'tinymce' => [
        'cdn' => 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js',
        'options' => [
            'autoresize_bottom_margin' => 50,
            'branding' => false,
            // 'content_css' => '/css/tinymce.css',
            // 'content_langs' => [
            //     ['title' => 'English', 'code' => 'en'],
            //     ['title' => 'Dutch', 'code' => 'nl'],
            // ],
            'contextmenu' => false,
            'file_picker_types' => 'file image media',
            'relative_urls' => false,
            'remove_script_host' => true,
            // 'document_base_url' => env('APP_URL'),
            // 'height' => 200, // Not used with autoresize
            'language_url' => 'https://cdn.jsdelivr.net/npm/tinymce-i18n@24.11.25/langs7/' . app()->getLocale() . '.js',
            'language' => app()->getLocale(),
            'license_key' => 'gpl',
            // 'link_default_target' => '_blank',
            'menubar' => false,
            'plugins' => 'accordion anchor autolink autoresize charmap code emoticons image link lists media searchreplace table visualblocks wordcount', // autosave codesample directionality fullscreen help preview visualchars importcss
            'promotion' => false,
            // 'skin' => 'oxide-dark',
            'style_formats' => [
                ['title' => 'H2', 'block' => 'h2'],
                ['title' => 'H3', 'block' => 'h3'],
                ['title' => 'H4', 'block' => 'h4'],
                ['title' => 'Quote', 'block' => 'blockquote'],
            ],
            'link_class_list' => [
                ['title' => 'Default', 'value' => ''],
                ['title' => 'Button', 'value' => 'button'],
            ],
            'toolbar_mode' => 'sliding',
            'toolbar_sticky_offset' => 0, // Doesn't seem to do anything due to our custom sticky toolbar implementation with alpine.js
            'toolbar_sticky' => true,
            'toolbar' => 'code visualblocks | undo redo | styles | bold italic | bullist numlist outdent indent | accordion | alignleft aligncenter alignright alignjustify | link anchor | image media table | charmap emoticons searchreplace', // codesample fullscreen help removeformat preview ltr rtl visualchars blocks wordcount language
            'ui_mode' => 'split',
        ],
    ],

];
