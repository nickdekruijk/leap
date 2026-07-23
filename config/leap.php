<?php

use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Livewire\Profile;
use NickDeKruijk\Leap\Livewire\Roles;
use NickDeKruijk\Leap\Livewire\User;
use NickDeKruijk\Leap\Navigation\Logout;

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
    | title
    |--------------------------------------------------------------------------
    |
    | The title of the application used as html <title> and shown in the
    | browser tab.
    |
    */
    'title' => '{module} - Admin @ '.config('app.name'),

    /*
    |--------------------------------------------------------------------------
    | auth_2fa
    |--------------------------------------------------------------------------
    |
    | Per-user two factor authentication. Users choose one method: a TOTP
    | authenticator app (Google Authenticator, 1Password, etc.), powered by
    | Laravel Fortify, or a 6-digit code emailed at login. Each user enrolls
    | individually; a valid code is always required to activate TOTP 2FA
    | before the login gate starts enforcing it. The 'email' sub-array only
    | gates whether the email method can be enabled from Profile; users who
    | already confirmed it stay protected even if this is turned off.
    |
    */
    'auth_2fa' => [
        'enabled' => true, // Enable per-user two factor authentication
        'required' => false, // Force every user without a configured 2FA method into enrollment-only mode (only Profile reachable) until they set one up
        'email' => [
            'enabled' => false, // Allow email as an alternative two factor method in Profile
            'expires' => 15, // Minutes a mailed code remains valid
            'resend_throttle' => 60, // Seconds between resend requests
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | auth_passkeys
    |--------------------------------------------------------------------------
    |
    | Passwordless login with passkeys (WebAuthn), powered by Laravel's own
    | passkeys package. Users register one or more passkeys from Profile and
    | can then sign in with just their device biometrics/PIN, no password or
    | two factor challenge needed. Disable to hide the passkey UI entirely.
    |
    */
    'auth_passkeys' => [
        'enabled' => true,
        'satisfies_2fa_requirement' => true, // If true, a user with a registered passkey is exempt from mandatory 2FA enrollment (auth_2fa.required)
    ],

    /*
    |--------------------------------------------------------------------------
    | password_reset
    |--------------------------------------------------------------------------
    |
    | Enable the forgot/reset password flow. This uses Laravel's password
    | broker and requires a 'password_reset_tokens' table (present in the
    | default Laravel schema). Only enable this if mail is actually
    | configured, otherwise reset links will never arrive.
    |
    */
    'password_reset' => false,

    /*
    |--------------------------------------------------------------------------
    | password_broker
    |--------------------------------------------------------------------------
    |
    | The password broker to use for the forgot/reset password flow. Null uses
    | the default broker defined in config/auth.php (usually 'users').
    |
    */
    'password_broker' => null,

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
        FileManager::class,
        Profile::class,
        Logout::class,
        Roles::class,
        User::class,
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
    | An array of css files to include in the head of the app layout. These
    | are concatenated into a single file and cached. If path is ommited the
    | file in the resources/css directory is used.
    |
    */
    'css' => [
        'leap.css',
        'editor.css',
        'filemanager.css',
        // base_path('resources/css/custom.css'),
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
        'allowed_extensions' => [
            'csv',
            'doc',
            'docx',
            'gif',
            'jpeg',
            'jpg',
            'json',
            'm4a',
            'md',
            'mp3',
            'mp4',
            'mpg',
            'numbers',
            'ogg',
            'pages',
            'pdf',
            'png',
            'svg',
            'xls',
            'xlsx',
            'zip',
        ],
        'disk' => 'public', // Must refer to a disk defined in config/filesystems.php, e.g. 'local' or 'public'
        'image_crop_enabled' => true, // true = all bitmap formats (svg is always excluded, it's vector). false disables. Or an array of extensions for finer control, e.g. ['jpeg', 'jpg', 'png', 'webp'] to exclude gif (cropping breaks animation)
        'image_focus_enabled' => true, // true = all bitmap formats (svg is always excluded, it's vector). false disables. Or an array of extensions for finer control, e.g. ['jpeg', 'jpg', 'png', 'webp', 'gif']
        'upload_max_filesize' => '128G', // Maximum size of an uploaded file in bytes, still limited by php.ini upload_max_filesize and post_max_size
    ],

    /*
    |--------------------------------------------------------------------------
    | locales
    |--------------------------------------------------------------------------
    |
    | Set to an associative array of locale codes to enable per-locale content
    | editing (e.g. alt text per language). When null, the current app locale
    | is used and content is stored as a plain string. When set to an array,
    | one input is shown per locale and content is stored as
    | ['nl' => '...', 'en' => '...']. Example:
    | 'locales' => ['nl' => 'Nederlands', 'en' => 'English']
    |
    */
    'locales' => null,

    /*
    |--------------------------------------------------------------------------
    | slug_follow_minutes
    |--------------------------------------------------------------------------
    |
    | How long after a record is created an unedited slug keeps silently
    | following its title in the editor. While within this window a title
    | change updates the derived slug automatically (you are still setting the
    | page up). After it — or once the slug has been edited by hand — a title
    | change instead offers an inline suggestion, so a live/indexed URL is
    | never changed without confirmation. Set to 0 to always ask.
    |
    */
    'slug_follow_minutes' => 60,

    /*
    |--------------------------------------------------------------------------
    | sitemap
    |--------------------------------------------------------------------------
    |
    | Models that contribute entries to the frontend sitemap.xml. Each must
    | implement NickDeKruijk\Leap\Contracts\Sitemapable (models using the
    | HasLocaleRouting trait get a default implementation for free). The Sitemap
    | helper (NickDeKruijk\Leap\Classes\Sitemap) merges the entries of every
    | listed model. Non-existent or non-Sitemapable entries are skipped. When
    | empty, the frontend template falls back to a page-tree-only sitemap.
    | Example:
    | 'models' => [App\Models\Page::class, App\Models\Service::class],
    |
    */
    'sitemap' => [
        'models' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | content
    |--------------------------------------------------------------------------
    |
    | The frontend template's listed content types: models rendered as a row of
    | cards (a teaser on a page, a filterable overview of their own, and a detail
    | page each). The key is the route/section slug; array order is the section
    | order in the Page editor and the menu. This registry is the single source
    | of truth — PageController, the Page resource, live search and sitemap.xml
    | all read it. Add a type with `php artisan leap:content <Name>` (it appends
    | a line here), or by hand. Non-existent classes are skipped. Example:
    | 'content' => [
    |     'news' => App\Models\News::class,
    |     'events' => App\Models\Event::class,
    | ],
    |
    */
    'content' => [],

    /*
    |--------------------------------------------------------------------------
    | ai
    |--------------------------------------------------------------------------
    |
    | AI providers and per-task configuration. Provider credentials (API keys)
    | are the only env vars needed; the per-task provider/model are structural
    | project choices set as literals below.
    |
    | A task is enabled when its 'provider' is set AND that provider's api_key
    | is non-empty. When 'model' is null the sensible default for the chosen
    | provider is used (see NickDeKruijk\Leap\Classes\AiTask::defaultModel()) —
    | only set a literal model to override it.
    |
    */
    'ai' => [
        // Shared provider credentials — the only env vars this feature needs.
        'providers' => [
            'gemini' => ['api_key' => env('GEMINI_API_KEY')],
            'claude' => ['api_key' => env('ANTHROPIC_API_KEY')],
            'openai' => ['api_key' => env('OPENAI_API_KEY')],
            'deepl' => ['api_key' => env('DEEPL_API_KEY')], // translation only; no vision
        ],

        // Request timeout in seconds for provider calls, and a per-user rate limit
        // (max AI actions per minute) — each call hits a paid third-party API.
        // Image generation takes tens of seconds, so it raises PHP's own execution
        // limit to this value plus 30. Note that a web server usually gives up before
        // PHP does (nginx defaults to 60 seconds), so raising this far above a minute
        // needs the server's proxy timeout raised with it.
        'timeout' => 60,
        'rate_limit' => 30,

        // Generate image alt texts (per locale) in the filemanager.
        'alt_text' => [
            'provider' => null, // 'gemini' | 'claude' | 'openai' (vision required)
            'model' => null,    // null => gemini-2.5-flash / claude-haiku-4-5 / gpt-4o-mini
        ],

        // Translate editor content (per field or all fields) into the active locale.
        'translate' => [
            'provider' => null, // 'gemini' | 'claude' | 'openai' | 'deepl'
            'model' => null,    // null => provider default; override e.g. 'claude-sonnet-5'
            // 'max_tokens' => 8192, // chat providers: cap the reply; raise for long pages
        ],

        // Generate an image from a prompt: in the editor next to a media field's browse
        // button (prefilled from the section's own content) and in the file manager.
        'image' => [
            'provider' => null, // 'gemini' | 'openai' (Claude and DeepL cannot generate images)
            'model' => null,    // null => gemini-2.5-flash-image / gpt-image-1-mini
            // Where generated images are stored on the filemanager disk. {module} is the
            // module's folder name, so a Page lands in pages/ and a News item in news/.
            // Set a literal ('ai') to collect them in one folder, or combine: 'ai/{module}'.
            'folder' => '{module}',
            'aspect_ratios' => ['16:9', '4:3', '1:1', '3:4'], // offered in the generate dialog; the result is cropped to the chosen one
            'quality' => null,       // openai only: 'low' | 'medium' | 'high' (null = the provider's 'auto'). Changes the price per image by up to 35x, so setting it explicitly also makes the cost estimate exact
            'max_width' => 1600,     // generated images are scaled down to this width
            'jpeg_quality' => 82,    // encoding quality of the stored JPEG
            'alt_text' => true,      // also generate alt text for the new image when the alt_text task is configured
            'style' => null,         // optional house-style sentence appended to every prompt
        ],

        // What a call costs, used to show an estimate before generating and the actual
        // amount after. Rates per model ship with the package (see AiTask::DEFAULT_PRICING)
        // so they can be kept current with an update instead of going stale in every
        // published config; list a model here only to override it. Values are US dollars
        // per million tokens, with 'estimate' the indicative price of a single image.
        // These are NOT billed amounts: they are computed, exclude VAT and ignore any
        // free tier. A model with neither a default nor an entry here shows no price.
        // Where a provider charges by quality, 'estimate' is a figure per quality.
        // 'pricing' => [
        //     'gemini-2.5-flash-image' => ['input' => 0.30, 'output' => 30.00, 'estimate' => 0.039],
        //     'gpt-image-1-mini' => ['input' => 2.00, 'output' => 8.00, 'estimate' => ['low' => 0.006, 'medium' => 0.015, 'high' => 0.052]],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | consent
    |--------------------------------------------------------------------------
    |
    | Cookie consent for the frontend template: which categories a visitor is asked
    | about, and which cookies each one actually sets.
    |
    | The cookie list is a manifest, not decoration. A scanner can see that a cookie
    | exists, but never what it is for or how long it is kept — and that is exactly
    | what a privacy statement has to state. So it is declared here, the cookie table
    | on the privacy page renders it, and a browser test holds it to the truth: any
    | cookie that turns up without being declared fails the build.
    |
    | enabled  false = no banner at all. Every category then falls back to `default`.
    | default  What a category is worth when nobody was asked: 'denied' (a site with
    |          no trackers) or 'granted' (knowingly skipping the question — not
    |          GDPR-proof, but sometimes the deliberate choice).
    | granular true = a preferences screen per category. false = accept all / refuse.
    |          All-or-nothing is fine with a single optional category — a screen with
    |          one switch is theatre — but with several distinct purposes a visitor is
    |          entitled to refuse the marketing and keep the analytics.
    |
    | Add a service and the registry's fingerprint changes, which expires the consent
    | already given: it covered what was on the table at the time, and no longer does.
    |
    */
    'consent' => [
        'enabled' => env('LEAP_CONSENT', true),
        'default' => 'denied',
        'granular' => true,

        'categories' => [

            'necessary' => [
                'necessary' => true,
                'services' => [
                    [
                        'name' => 'Website',
                        'provider' => null, // first party
                        'cookies' => [
                            ['name' => 'XSRF-TOKEN', 'retention' => '2 hours'],
                            ['name' => '*-session', 'retention' => '2 hours'],
                            ['name' => 'consent', 'retention' => '6 months'],
                        ],
                    ],
                ],
            ],

            // Loaded from the "scripts_analytics" setting, and by the Matomo
            // integration when leap.consent.matomo is configured.
            'analytics' => [
                'services' => [
                    [
                        'name' => 'Matomo',
                        'provider' => 'Matomo (self-hosted)',
                        'cookies' => [
                            ['name' => '_pk_id*', 'retention' => '13 months'],
                            ['name' => '_pk_ses*', 'retention' => '30 minutes'],
                            // Matomo's own record that consent was given. Found by the
                            // browser test, not by reading the docs — which is the whole
                            // point of holding the registry to the truth.
                            ['name' => 'mtm_cookie_consent', 'retention' => '30 years'],
                        ],
                    ],
                ],
            ],

            // Embedded video. Nothing is requested until a visitor presses play, so
            // no cookie is set on this site — the point is the data that reaches the
            // provider once they do.
            'embeds' => [
                'services' => [
                    [
                        'name' => 'YouTube',
                        'provider' => 'Google Ireland Ltd.',
                        'cookies' => [],
                    ],
                    [
                        'name' => 'Vimeo',
                        'provider' => 'Vimeo Inc. (VS)',
                        'cookies' => [],
                    ],
                ],
            ],

        ],

        /*
         | Matomo, if you use it. Its cookieless mode is worth supporting properly:
         | with requireCookieConsent it measures every visitor without setting a
         | cookie, so the cookie law is never triggered and the people who refuse are
         | still counted. On consent it switches its cookies on for better figures.
         |
         | Anything else — GA4, Meta, Hotjar — goes in the "scripts_<category>"
         | setting instead. Those cannot run cookieless and belong behind consent.
         |
         | Leave url empty to render nothing.
         */
        'matomo' => [
            'url' => env('MATOMO_URL'),
            'site_id' => env('MATOMO_SITE_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ace
    |--------------------------------------------------------------------------
    | Ace code editor options like CDN and version to use.
    | cdn: By default the latest 1.x version.
    |
    */
    'ace' => [
        'cdn' => 'https://cdn.jsdelivr.net/npm/ace-builds@1/src-min-noconflict/ace.min.js',
        'options' => [
            'maxLines' => 10,
            'minLines' => 2,
            'mode' => 'ace/mode/json',
            'theme' => 'ace/theme/eclipse',
            'wrap' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | tinymce
    |--------------------------------------------------------------------------
    |
    | Settings for the TinyMCE rich-text editor used by Attribute::richtext().
    |
    | cdn:     The TinyMCE build to load. Defaults to the latest 7.x version.
    | options: Passed as JSON to tinymce.init(). See the TinyMCE 7 docs:
    |          https://www.tiny.cloud/docs/tinymce/7/
    |
    | lazy / lazy_sections: "Lazy" fields show their rendered HTML as a
    | click-to-edit preview and only start TinyMCE when clicked (and drop back
    | to the preview on save), so an editor with many rich-text fields opens
    | fast. 'lazy' applies to standalone top-level fields (default false = start
    | TinyMCE immediately, as before); 'lazy_sections' applies to rich-text
    | inside repeatable sections (default true), which is where the slowdown
    | usually comes from.
    |
    */
    'tinymce' => [
        'cdn' => 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js',
        'lazy' => false,
        'lazy_sections' => true,
        'options' => [
            'autoresize_bottom_margin' => 50,
            // Class on the editor iframe <body>, so a content_css scoped under
            // .tinymce (like the template's) also styles the click-to-edit preview.
            'body_class' => 'tinymce',
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
            'language_url' => 'https://cdn.jsdelivr.net/npm/tinymce-i18n@24.11.25/langs7/'.app()->getLocale().'.js',
            'language' => app()->getLocale(),
            'license_key' => 'gpl',
            // 'link_default_target' => '_blank',
            'menubar' => false,
            // 'paste_as_text' => true,
            'plugins' => 'accordion anchor autolink autoresize charmap code emoticons image link lists media searchreplace table visualblocks wordcount', // autosave codesample directionality fullscreen help preview visualchars importcss
            'promotion' => false,
            // 'skin' => 'oxide-dark',
            'style_formats' => [
                ['title' => 'H2', 'block' => 'h2'],
                ['title' => 'H3', 'block' => 'h3'],
                ['title' => 'H4', 'block' => 'h4'],
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
