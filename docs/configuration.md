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
| `cache` | `true` | Cache the frontend page tree. See [caching.md](caching.md). |
| `auth_2fa` | *(array)* | Two factor authentication settings. See [permissions-and-auth.md](permissions-and-auth.md). |
| `auth_passkeys` | *(array)* | Passkey settings. |
| `password_reset` | `true` | Enable the forgot/reset password flow. |
| `credentials` | `['email', 'password']` | Login fields. |
| `css` | *(array)* | SCSS files compiled and served for the panel UI. |
| `login_image` | | Background image on the login screen. |
| `logging` | *(array)* | Audit logging of admin actions (enable, skip actions/modules, IP anonymisation). |
| `filemanager` | *(array)* | File manager allowed extensions and upload limits. |
| `ace` / `tinymce` | *(array)* | Options for the code and rich-text editors. `tinymce.lazy` / `tinymce.lazy_sections` toggle click-to-edit rich-text — see [attributes.md](attributes.md#lazy-rich-text). |

Read any value with `config('leap.<key>')`, or inspect it with
`php artisan config:show leap.<key>`.
