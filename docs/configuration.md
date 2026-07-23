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
