# Leap: Laravel Easy Admin Panel

Leap is a Laravel package that provides a simple admin panel for your Laravel application or use it to start your next Spa or SaaS application. It is build with Livewire v3 components and designed to be easy to use and customizable.

## Installation

Begin by installing this package with composer.

`composer require nickdekruijk/leap`

### Laravel installation

Publish the config file if the defaults doesn't suite your needs:

```php artisan vendor:publish --provider="NickDeKruijk\Leap\ServiceProvider"```

### Config
See the config file at `config/leap.php`

### Roles and permissions

Leap manages module/resource permissions through roles assigned to users, so
your authenticatable model **requires** the `HasRoles` trait — without it the
panel throws a `Call to undefined method` error when checking permissions:

```php
use NickDeKruijk\Leap\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

The `leap_roles` and `leap_role_user` tables are added by the package
migrations (when `leap.migrations` is enabled). Roles are managed from the
**Roles** module in the panel.

### Two factor authentication

Leap supports per-user two factor authentication (TOTP) with recovery codes,
powered by [Laravel Fortify](https://laravel.com/docs/fortify). This is enabled
by default (`leap.auth_2fa.enabled`), so your authenticatable model **requires**
the `TwoFactorAuthenticatable` trait — without it the Profile screen throws a
`Call to undefined method` error:

```php
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use TwoFactorAuthenticatable;
}
```

The required `two_factor_secret`, `two_factor_recovery_codes` and
`two_factor_confirmed_at` columns are added to your users table by the package
migrations (when `leap.migrations` is enabled). Users can enable, confirm and
disable two factor authentication from the **Profile** screen in the panel.
Disable the feature entirely with `leap.auth_2fa.enabled`.

### Passkeys

Leap supports passwordless login with passkeys (WebAuthn), powered by
[Laravel's passkeys package](https://github.com/laravel/passkeys-server).
This is enabled by default (`leap.auth_passkeys.enabled`), so your
authenticatable model **requires** the `PasskeyAuthenticatable` trait and the
`PasskeyUser` contract — without it the Profile screen and the passkey
endpoints throw errors:

```php
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use PasskeyAuthenticatable;
}
```

Use the `Laravel\Passkeys` namespace directly, not the `Laravel\Fortify` wrapper of
the same trait/contract: that wrapper only exists on very recent Fortify
releases that bundle passkeys support, so depending on it breaks on older
`^1.19` installs.

The `passkeys` table is added by the package migrations (when
`leap.migrations` is enabled). Users register one or more passkeys from the
**Profile** screen, then sign in from the login screen with just their
device's biometrics or PIN — no password or two factor challenge involved.
Disable the feature entirely with `leap.auth_passkeys.enabled`.

### Password reset

The forgot/reset password flow is enabled by default (`leap.password_reset`) and
uses Laravel's password broker, so a `password_reset_tokens` table (part of the
default Laravel schema) and a configured mailer are required.
