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
    | Image to show on the login screen. Null (the default) shows no image and
    | the login form is centred on its own. Any URL or local path works, for
    | example a random photo from picsum.photos:
    |
    |   'login_image' => 'https://picsum.photos/1140/996',
    |
    | Off by default because it would make every login page call a third party.
    |
    | The image viewport is 380x332 pixels and zooms to 1.5 magnification, times
    | two for retina screens. So 1140x996 pixels is a good size.
    |
    */
    'login_image' => null,

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
    | not_found_log
    |--------------------------------------------------------------------------
    | Writes a line to the application log when a page is asked for that does not
    | exist. Off by default: a missing page is not a fault of the application, and
    | most 404s are a scanner walking a wordlist rather than anything to fix.
    |
    | Switch it on when you are chasing broken links — after a migration, or when
    | a redirect map is being written. The line carries the path and, trimmed, the
    | referer that led there, which is the pair that says which link to fix and
    | where it lives.
    |
    | The IP address is anonymized: 198.51.100.xxx, not the whole thing. Together
    | with the user agent that is what separates a visitor who followed a dead
    | link from a bot working through a wordlist — which is the first thing you
    | want to know and the last thing a bare path can tell you. Switch
    | 'ip_address_anonymized' off for the whole address, and know why you did.
    | The same key names as the 'logging' block above, with the same meaning.
    |
    | The referer is kept whole, query string and all: "?page=3" says which page
    | of a listing carried the dead link and "?utm_source=..." says the newsletter
    | did, which is the question. Browsers have defaulted to
    | strict-origin-when-cross-origin for years, so a referer from somewhere else
    | is usually just an origin anyway — the full URLs are your own pages.
    | Set 'referer_query_string' to false to keep only the path.
    |
    | 'throttle_minutes' is how long the same path stays quiet after it has been
    | written once. Without it a scanner writes a line per guess: a log nobody can
    | read, on a disk that fills at whatever rate the scanner chooses.
    |
    | 'channel' names a logging channel from config/logging.php, so these lines can
    | go to a file of their own rather than into the middle of everything else.
    | Null uses the default channel.
    |
    */
    'not_found_log' => [
        'enabled' => env('LEAP_NOT_FOUND_LOG', false),
        'channel' => env('LEAP_NOT_FOUND_LOG_CHANNEL'), // null = the default channel
        'level' => 'info',
        'throttle_minutes' => 60,
        'referer' => true, // Log the page that linked here — the reason this is useful
        'referer_query_string' => true, // Include its query string; false keeps only the path
        'ip_address' => true, // Log the visitor's IP address
        'ip_address_anonymized' => true, // Take the last part off it (192.168.1.xxx)
        'user_agent' => true, // Log the user agent string — with the IP, this is what spots a bot
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
        'sanitize_svg' => true, // Strip scripts, event handlers and javascript: URLs from uploaded SVGs. SVGs are served same-origin from the disk above, so an unsanitized SVG can run in an admin's session (stored XSS)
        // What happens when an upload has the name of a file that is already there and different contents. false stores it beside the old one as name-1.jpg, which is the safe answer: nothing that was on a page changes without someone saying so. true writes over it instead and everything pointing at that media keeps pointing at it, now showing the new picture -- which is what "upload a new version" is usually meant to do, but it changes every page using it at once. Only for someone with update permission; anyone else gets the numbered copy
        'upload_replace' => false,
        // Run the name of a new file through Str::slug, extension kept, so "Zomer 2024, strand.jpg" is stored as "zomer-2024-strand.jpg". A space or a comma in a filename is what breaks a srcset, and this keeps them from getting in. Applies to uploads, renames and crop-as-new; files already on the disk are left alone, their URLs are encoded on the way out
        'slug_uploads' => true,
        'image_crop_enabled' => true, // true = all bitmap formats (svg is always excluded, it's vector). false disables. Or an array of extensions for finer control, e.g. ['jpeg', 'jpg', 'png', 'webp'] to exclude gif (cropping breaks animation)
        'image_focus_enabled' => true, // true = all bitmap formats (svg is always excluded, it's vector). false disables. Or an array of extensions for finer control, e.g. ['jpeg', 'jpg', 'png', 'webp', 'gif']
        'upload_max_filesize' => '128G', // Maximum size of an uploaded file in bytes, still limited by php.ini upload_max_filesize and post_max_size
    ],

    /*
    |--------------------------------------------------------------------------
    | images
    |--------------------------------------------------------------------------
    |
    | Resized copies of images on the filemanager disk, written on first request
    | and served straight off disk by the web server from then on. Every URL
    | carries a prefix of the file's sha256, so replacing a file changes every
    | URL pointing at it: there is no cache to invalidate, browsers and CDNs
    | included, only orphaned copies to prune.
    |
    | Off by default in 1.x. A site still running nickdekruijk/imageresize would
    | otherwise have two packages answering overlapping routes. Turn it on, then
    | drop imageresize -- see docs/images.md.
    |
    */
    'images' => [

        // Master switch. false = no route, no disk, no resizing: Media::url()
        // returns the original and <x-leap::responsive-image> emits a plain img.
        'enabled' => false,

        // First URL segment, and the name of the symlink in public/ that points
        // at the copies. Must not collide with an application route or another
        // package's.
        'route' => 'img',

        // Where resized copies are written. Leap registers this disk itself,
        // rooted at storage/app/leap-images, unless filesystems.disks already
        // defines the name -- so nothing has to be added to
        // config/filesystems.php. It also registers the link that
        // `php artisan storage:link` makes from public/<route> to it, which is
        // what lets the web server serve a copy without PHP; run that after
        // installing, and on every deploy that builds a new public/. Forget it
        // and nothing breaks, it is only slower, so `leap:images` says when the
        // link is not there.
        //
        // In storage rather than in public because a release-based deploy
        // replaces public/ wholesale, which would throw away every resized copy
        // on the site each time. In its own directory rather than in
        // storage/app/public because that one has a link of its own, and every
        // copy would answer at /storage/<route>/... as well: one picture, two
        // addresses. Name another disk to override, but a disk the
        // web server cannot serve statically (s3, or anything outside public/)
        // needs 'eager' below, since the fallback that generates on first
        // request only fires for a URL the web server tried and missed.
        'disk' => 'leap-images',

        // Where originals are read from. null = leap.filemanager.disk.
        'source_disk' => null,

        // Characters of the sha256 that go in the URL. 8 is 32 bits: for a
        // collision to serve the wrong picture two different files would have to
        // share both the prefix and the path, which the path already prevents.
        'hash_length' => 8,

        // Generate every preset in a queued job as soon as a file is stored or
        // changed, instead of on first request. Required for a disk the web
        // server cannot serve statically. Needs a queue worker.
        'eager' => false,
        'queue' => null,

        // Seconds a generation holds its lock. A second request for the same URL
        // waits for it rather than decoding the same original all over again.
        'lock_seconds' => 10,

        // Refuse to decode an original above this many pixels and serve it
        // as-is, rather than let GD blow past memory_limit and take the worker
        // down with it (GD needs roughly width * height * 4 bytes). null
        // disables the guard.
        'max_source_pixels' => 40_000_000,

        // How long a generated copy may be cached. Its URL changes whenever the
        // file does, so it is immutable by construction.
        'max_age' => 31536000,

        // What a preset that does not say otherwise gets.
        'defaults' => [
            'fit' => 'contain',         // contain = fit within width/height, ratio kept | cover = crop to exactly that box
            'height' => null,           // null with fit=contain means the width is the only constraint
            'quality' => 80,
            // How hard the webp encoder works, 0 to 6. Buys about a tenth of the
            // file size at the same quality for about half again the encoding
            // time -- paid once, on a file the web server then serves forever.
            // libwebp's "method", so it needs the Imagick driver; the GD one has
            // no equivalent and ignores this. null leaves the default (4).
            'effort' => 6,
            // One format, or several. A string encodes every copy that way, as
            // before. An array is an ordered offer, best first: the component
            // wraps its <img> in a <picture> and gives each entry a <source>,
            // so the browser takes the first type it recognises.
            //
            //     'format' => 'webp',
            //     'format' => ['avif' => ['quality' => 55], 'webp' => []],
            //
            // Options may be set per format, and want to be: avif reaches the
            // same picture at a markedly lower number than webp, and carrying
            // webp's 80 over would make the avif copy the larger of the two.
            //
            // A lone URL -- $media->url(1200), an og:image, a video poster --
            // cannot negotiate, so it takes the *last* entry: the list is best
            // first, so the last is the most compatible. Handing a scraper avif
            // because it was listed first is how a social preview goes blank.
            //
            // A format the driver cannot encode is left out of the markup rather
            // than offered and served broken -- a <picture> commits to the first
            // type it recognises and never falls back to the <img>. GD has no
            // avif encoder, so avif needs image.driver on Imagick.
            //
            // null keeps the source format. 'webp' | 'jpg' | 'png' | 'avif'
            'format' => 'webp',

            // What the <img> inside a <picture> gets: the copy a browser lands
            // on when it matched no <source> at all. Only consulted when
            // 'format' above is a list; one format needs no fallback.
            //
            // An override on top of the preset itself, so width, height and fit
            // come along -- a square preset whose fallback was a loose 1200
            // would hand a legacy browser a different shape than every <source>
            // above it. Narrow it with 'width' where the full size is wasted on
            // the few who land here.
            //
            // format: null keeps the source's own. Forcing jpg would flatten
            // every transparent png onto black; avif and webp both carry alpha,
            // so this is the only step that could lose it.
            //
            // It is also what Google Images indexes, since Google reads the
            // <img src> and not a srcset -- so do not make it tiny.
            'fallback' => ['format' => null],
            // Source extensions encoded losslessly. Empty on purpose: measured against
            // quality 80, lossless webp is five times the bytes for a screenshot and
            // eight for a photograph that happens to be a PNG, which is not a trade to
            // make on anyone's behalf. Add 'png' where the images really are line art,
            // UI or text on flat colour, and the artefacts show
            'lossless_from' => [],
            'upscale' => false,         // An original smaller than the preset is passed through, not blown up
            'blur' => null,             // 1-100
            'grayscale' => false,
        ],

        // The srcset ladder. Every width here is also a preset named after
        // itself, so $media->url(1200) works and <x-leap::responsive-image
        // :widths="[600, 900]"> needs no further configuration.
        'widths' => [600, 900, 1200, 1600, 1920, 2560],

        // Named presets, for what the ladder does not cover. Each may set any
        // key from 'defaults' plus 'width'. A name here wins over a width above.
        'presets' => [
            // 'square' => ['width' => 600, 'height' => 600, 'fit' => 'cover'],
            // 'og' => ['width' => 1200, 'height' => 630, 'fit' => 'cover', 'format' => 'jpg'],
        ],

        // Ladder <x-leap::responsive-image> uses when given no :widths. Separate
        // from 'widths' so the allowlist can be wider than the default output.
        'component_widths' => [600, 900, 1200, 1600],

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
    | locales_published
    |--------------------------------------------------------------------------
    |
    | Which of those languages the frontend serves. Null means all of them, so
    | nothing changes for a site that does not set it.
    |
    | The two lists answer different questions. 'locales' is what an editor can
    | write; 'locales_published' is what a visitor can read. A translation is
    | typed page by page over weeks, and until it is finished half a translated
    | site is worse than none: the prefix answers in a mixture of languages,
    | Google indexes it, and hreflang says the two versions are equals.
    |
    | So a language is added to 'locales' the day someone starts writing it, and
    | here the day it is finished. An unpublished locale keeps its editor, its
    | per-locale storage and its admin tabs, and has no URL: its prefix is not
    | consumed, so it falls through to a 404 like any unknown path.
    |
    | Reviewing one before it is published is not what this is for. That is the
    | admin's preview, which stays behind the same permissions as the module.
    |
    | 'locales_published' => ['en'],
    |
    */
    'locales_published' => null,

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
    | robots
    |--------------------------------------------------------------------------
    |
    | /robots.txt, rendered by leap rather than served from public/. The one line
    | it is really for is Sitemap:, and that has to be an absolute URL. A file in
    | git would have to hard-code one host and be wrong on every other
    | environment.
    |
    | It has one failure mode and it is silent: a file in public/robots.txt is
    | answered by the web server without PHP being asked anything, so the route
    | never runs and nothing says so. Delete that file. `php artisan leap:robots`
    | prints what a crawler gets and reports it when something is in the way.
    |
    | Publish resources/views/vendor/leap/robots.blade.php (tag: leap-views) to
    | write the file by hand instead.
    |
    */
    'robots' => [

        // Master switch. false = no route, and the site answers whatever is in
        // public/, or a 404.
        'enabled' => true,

        // Everything out of the crawl, and no Sitemap line. On by default
        // anywhere but production: a staging copy is the same site to a crawler,
        // and the duplicate gets picked as the canonical one often enough to
        // matter.
        //
        // Note what this does and does not do. It forbids crawling, not
        // indexing: a URL that is linked to can still be listed, without a
        // snippet, and a crawler that may not fetch the page never sees an
        // X-Robots-Tag: noindex either. So to get a staging URL back out of an
        // index, allow crawling and send that header instead. And to keep people
        // out, use HTTP auth in the web server: robots.txt is a request, not a
        // lock.
        //
        // APP_ENV is read straight from the environment rather than through
        // app()->environment(), because a published copy of this file is loaded
        // before the application knows what environment it is in.
        'disallow_all' => env('LEAP_ROBOTS_DISALLOW_ALL', env('APP_ENV', 'production') !== 'production'),

        // Paths kept out of the crawl: search results with a querystring, an
        // export endpoint: real pages that are not worth a crawl budget. These
        // are repeated in every group below, because a crawler obeys the most
        // specific group that names it and ignores every other one. Without the
        // repetition the list would apply to everyone except the crawlers named
        // by ai_crawlers.
        'disallow' => [],

        // The Sitemap line: a route name, a literal URL, or false for none. The
        // sitemap route belongs to the frontend and not to leap, so a name that
        // does not resolve leaves the line out rather than pointing at a 404.
        'sitemap' => 'sitemap',

        // The crawlers behind the answer engines. 'allow' names them in a group
        // of their own. What they may do does not change, allow-all already
        // allowed them, but writing it down keeps a later blanket Disallow from
        // taking them out along with everything else, and makes allowing them
        // read as a decision rather than as an oversight. 'disallow' keeps them
        // off the site, 'omit' leaves the group out.
        'ai_crawlers' => 'allow',
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

        // Whether the panel shows what a call costs: the estimate before generating and
        // the actual amount afterwards. Off by default — both figures are computed from
        // the rates below, never reported by the provider, so they exclude VAT and any
        // free tier, and an editor reading a price that is close but not the invoice is
        // usually worse served than one reading none. Turn it on where the person
        // generating is the person paying.
        'show_costs' => false,
        // Recording is a separate switch, and stays on: the amount is kept on the media
        // row (meta['ai']['cost']) for reporting whether or not anyone is shown it.
        // Nothing computes it later — off means that image's cost is gone for good.
        'record_costs' => true,

        // Chat models (alt_text and translate). Null takes the provider's default;
        // any model id the provider accepts works, for example:
        //   gemini => gemini-2.5-flash (default), gemini-2.5-pro
        //   claude => claude-haiku-4-5 (default), claude-sonnet-5, claude-opus-4-8
        //   openai => gpt-4o-mini (default), gpt-4o
        // No rates ship for chat models, so a call shows no price unless one is listed
        // under 'pricing' below. DeepL has no model to choose.

        // Generate image alt texts (per locale) in the filemanager.
        'alt_text' => [
            'provider' => null, // 'gemini' | 'claude' | 'openai' (vision required)
            'model' => null,    // null => gemini-2.5-flash / claude-haiku-4-5 / gpt-4o-mini
            // Longest side of the copy that is sent, in pixels. A photo off a camera does
            // not fit otherwise: providers cap an image at a few megabytes once base64
            // encoded, and base64 adds a third to whatever is on disk. 1568 is the size
            // above which the vision models resize server-side anyway, so anything larger
            // is paid for and thrown away. Set to null to send the original untouched.
            'max_width' => 1568,
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
            // The model + quality combinations offered in the generate dialog. The key is
            // the label ('low', 'medium' and 'high' are translated, anything else shows as
            // written), the value a model id with an optional ':quality' suffix — openai
            // only, gemini has no quality setting. Quality changes the price per image by
            // up to 35x, so naming it also makes the cost estimate exact.
            //
            // The provider follows from the model id and needs its api key above, so one
            // preset may run on Gemini and the next on OpenAI. No presets means no image
            // generation; one preset means there is nothing to pick and that one is simply
            // used; two or more show a picker.
            //
            // Models that ship with a price estimate, so the dialog can quote a cost up
            // front (any other id the provider accepts works too, but without one):
            //   gemini => gemini-2.5-flash-image, gemini-3.1-flash-lite-image,
            //             gemini-3.1-flash-image, gemini-3-pro-image
            //   openai => gpt-image-1-mini, gpt-image-1.5, gpt-image-2,
            //             gpt-image-1 (superseded)
            'presets' => [
                // 'standard' => 'gpt-image-1-mini',
                // 'low' => 'gemini-2.5-flash-image',
                // 'medium' => 'gpt-image-1-mini:medium',
                // 'high' => 'gemini-3-pro-image',
            ],
            // Where generated images are stored on the filemanager disk. {module} is the
            // module's folder name, so a Page lands in pages/ and a News item in news/.
            // Set a literal ('ai') to collect them in one folder, or combine: 'ai/{module}'.
            'folder' => '{module}',
            // The stored JPEG keeps what the model produced: its proportions (the dialog
            // asks for a shape, not an exact ratio, so nothing is cropped) and its
            // resolution. The file is the master; display sizes are a frontend concern.
            //
            // Set max_width to a number to cap it anyway — worth doing on a site that
            // serves the stored file straight into a page and uses a model that answers
            // at 2K or 4K. Encoding to JPEG always happens: providers answer in PNG,
            // several times the bytes of the same picture as JPEG.
            'max_width' => null,
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
    | on the privacy page renders it, and ConsentCookieDeclarationTest holds it to the
    | truth: a cookie the server sets without being declared here fails the build.
    | Cookies a script sets in the browser are only visible to a browser suite, so a
    | site that loads one checks it there.
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
                            // ':session' is filled in with config('session.cookie') when
                            // the registry is read, so the table shows the name that is
                            // really in the browser ("acme-session") instead of a pattern
                            // a visitor has to work out. It cannot be resolved here:
                            // config files are loaded alphabetically, so leap.php is read
                            // before session.php exists.
                            ['name' => ':session', 'retention' => '2 hours'],
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
