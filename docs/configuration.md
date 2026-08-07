# Configuration

Publish the config with:

```bash
php artisan vendor:publish --provider="NickDeKruijk\Leap\ServiceProvider" --tag=config
```

All keys live in `config/leap.php`. The most-used ones:

| Key | Default | Purpose |
| --- | --- | --- |
| `route_prefix` | `admin` | URL prefix the panel is served under. |
| `guard` | `web` | Auth guard used by the panel. |
| `title` | `{module} - Admin @ …` | Browser title template; `{module}` is replaced with the active module. |
| `app_modules` | `Leap` | Directory under `app/` scanned for modules (`app/Leap`). |
| `default_modules` | *(array)* | Extra module classes to register (packages append here). |
| `migrations` | `true` | Run the package migrations automatically. |
| `table_prefix` | `leap_` | Prefix for the package's own tables. |
| `locales` | `null` | `null` = monolingual; an assoc array enables per-locale editing. See [multilingual.md](multilingual.md). |
| `sitemap` | `['models' => []]` | Extra models that contribute to `sitemap.xml` (each `Sitemapable`). The template also adds every `content` type automatically. |
| `content` | `[]` | The template's listed content types, `slug => Model::class`. Managed by `leap:content`. See [content-types.md](content-types.md). |
| `images` | *(array)* | Resized copies of images on the filemanager disk. Off by default. See [images.md](images.md). |
| `auth_2fa` | *(array)* | Two factor authentication settings. See [permissions-and-auth.md](permissions-and-auth.md). |
| `auth_passkeys` | *(array)* | Passkey settings. |
| `password_reset` | `true` | Enable the forgot/reset password flow. |
| `credentials` | `['email', 'password']` | Login fields. |
| `css` | *(array)* | CSS files concatenated and served for the panel UI. See [Theming](#theming) below. |
| `login_image` | `null` | Image on the login screen. `null` shows none; any URL or local path works (the config comment has a `picsum.photos` example). |
| `logging` | *(array)* | Audit logging of admin actions (enable, skip actions/modules, IP anonymisation). |
| `filemanager` | *(array)* | Allowed extensions, upload limits, and `image_crop_enabled`/`image_focus_enabled` (`true` = every bitmap format, an array for finer control, `false` to disable — both default to `true`). |
| `ace` / `tinymce` | *(array)* | Options for the code and rich-text editors. Both load from a jsDelivr CDN by default; `ace.cdn` / `tinymce.cdn` take any URL, so point them at a self-hosted copy if you would rather not call out. `tinymce.lazy` / `tinymce.lazy_sections` toggle click-to-edit rich-text — see [attributes.md](attributes.md#lazy-rich-text). |
| `ai` | *(array)* | AI providers + per-task config for alt-text generation and translation (disabled by default). See [ai.md](ai.md). |

Read any value with `config('leap.<key>')`, or inspect it with
`php artisan config:show leap.<key>`.

## What a published config has to contain

Nothing in particular. Your `config/leap.php` only has to hold what you want to
differ; leap fills in the rest from the config it ships, nested keys included. So a
config published two releases ago keeps working, and a key a later release adds
arrives at its documented default instead of as `null`.

This is deeper than Laravel's own `mergeConfigFrom`, which merges top-level keys only
and would let a published `ai` section hide every key added below it since. **Lists are
the exception, deliberately:** an array with numeric keys replaces its counterpart
whole, so trimming `default_modules` to two entries leaves two, and
`'presets' => []` means none. Only arrays keyed by name are combined.

You are free to delete anything you have not changed:

```php
return [
    'route_prefix' => 'app',
    'ai' => [
        'image' => ['presets' => ['standard' => 'gpt-image-1-mini']],
    ],
];
```

`php artisan config:show leap` prints the merged result, which is what the application
reads — worth checking after an upgrade if a default surprises you.

## Logging a missing page

`leap.not_found_log` writes a line when a page is asked for that is not there. **Off by
default**: a missing page is not a fault of the application, and most 404s are a scanner
working through a wordlist rather than anything to fix. Switch it on while you are chasing
broken links — after a migration, or when a redirect map is being written.

```php
'not_found_log' => [
    'enabled' => env('LEAP_NOT_FOUND_LOG', false),
    'channel' => env('LEAP_NOT_FOUND_LOG_CHANNEL'),
    'level' => 'info',
    'throttle_minutes' => 60,
    'referer' => true,
    'referer_query_string' => true,
    'ip_address' => true,
    'ip_address_anonymized' => true,
    'user_agent' => true,
],
```

A line answers two questions — which link is broken and where does it live, and was this a
visitor or a machine:

```
[2026-08-07 11:04:12] production.INFO: 404 /oude-pagina {"referer":"https://elders.example/artikel","ip":"198.51.100.xxx","user_agent":"Mozilla/5.0 (compatible; SomeBot/2.1)"}
```

The second question is why the address and the user agent are on: a bare path cannot tell
a visitor who followed a dead link from a bot working through a wordlist, and that answer
decides whether there is anything to fix at all.

**The address is anonymized** — `198.51.100.xxx`, and for IPv6 the last two groups. Enough
to tell one network from another, which is what a log is ever asked; not enough to tell
one person from another. Switching `ip_address_anonymized` off gives you the whole thing,
and is worth doing on purpose rather than by leaving a default alone. `ip_address` and
`user_agent` are separate switches, so a site can keep the networks and drop the browser
strings. The same key names as the `logging` block above, with the same meaning.

The referer is kept whole. The query string is usually part of the answer rather than
noise — `?page=3` says which page of a listing carried the dead link, `?utm_source=…` says
the newsletter did — and it is nearly always one of your own URLs: browsers have defaulted
to `strict-origin-when-cross-origin` for years, so a referer from somewhere else arrives
as a bare origin with no path and no query at all.

Set `referer_query_string => false` to keep only the path, for a site whose own URLs carry
something it would rather not have in a log, or `referer => false` to leave the referer
out entirely — though that takes most of the value with it.

`throttle_minutes` is how long the same path stays quiet after it has been written once.
Set it to `0` to write every request; leave it alone and a scanner cannot fill the disk at
a rate it chooses.

`channel` names a channel from `config/logging.php`, so these lines can go somewhere of
their own rather than into the middle of everything else:

```php
// config/logging.php
'notfound' => ['driver' => 'daily', 'path' => storage_path('logs/404.log'), 'days' => 14],
```

Leave it null and they go to the default channel.

Note that this hangs off the exception handler's `render()`, not `report()`. Symfony's
`HttpException` is on Laravel's internal do-not-report list, so a `report()` callback is
never handed a 404 at all — and taking it off that list to reach one hands every 403 and
every `abort()` to whatever else reports, Sentry included. Worth knowing before writing
your own.

## Theming

The panel's CSS (`leap.css`, `filemanager.css`, `editor.css`) is plain CSS — no build
step, no SCSS. Colors, spacing and other repeated values are CSS custom properties
declared once in `:root` (`--leap-blue`, `--leap-header-background`,
`--leap-button-bg`, `--spacing`, …). To re-theme, override the variables you need in
your own stylesheet loaded after `AssetController::cssLink()`:

```css
:root {
    --leap-header-background: #1a1a2e;
    --leap-button-bg: #444;
}
```

No recompile needed — this is plain CSS cascade. For structural overrides you can
still replace an entire file: drop a same-named file in `resources/css/leap/` (e.g.
`resources/css/leap/leap.css`) and it takes priority over the package's own copy, per
the `css` array above.
