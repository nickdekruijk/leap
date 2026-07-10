# Installation

## Requirements

- PHP 8.3–8.4
- Laravel 12 or 13
- Livewire 3.7 or 4.1

## Install

```bash
composer require nickdekruijk/leap
```

Publish the config if you want to change the defaults:

```bash
php artisan vendor:publish --provider="NickDeKruijk\Leap\ServiceProvider" --tag=config
```

The package ships its own migrations (roles, logs, media, and the Fortify/passkey
columns). They run automatically when `leap.migrations` is `true` (the default).
Run them with:

```bash
php artisan migrate
```

## Prepare your user model

Leap needs a few traits/contracts on your authenticatable model. See
[permissions-and-auth.md](permissions-and-auth.md) for details; the minimum is:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use NickDeKruijk\Leap\Traits\HasRoles;

class User extends Authenticatable implements PasskeyUser
{
    use HasRoles;                   // required: module/resource permissions
    use TwoFactorAuthenticatable;   // required unless leap.auth_2fa.enabled = false
    use PasskeyAuthenticatable;     // required unless leap.auth_passkeys.enabled = false
}
```

## Log in

The panel is served under the `leap.route_prefix` prefix (default `admin`), e.g.
`https://your-app.test/admin`. Create a user and assign a role from the **Roles**
module, or use `php artisan leap:user` to create an admin.

## Frontend template (optional)

To scaffold a ready-made public website (pages, navigation, sections, search,
footer, SEO, sitemap) into your project:

```bash
php artisan leap:template
```

See [template.md](template.md).
