# Permissions and authentication

## Roles and permissions

Leap authorises every module/resource through **roles** assigned to users, so your
authenticatable model **requires** the `HasRoles` trait — without it the panel throws a
`Call to undefined method` error when checking permissions:

```php
use NickDeKruijk\Leap\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

The `leap_roles` and `leap_role_user` tables are added by the package migrations (when
`leap.migrations` is enabled). Roles are managed from the **Roles** module in the
panel. Each role grants per-module `read` / `create` / `update` / `delete`, or
`all_permissions` / `all_modules` wildcards. A user without `read` on a module does not
see it and receives a 404 (so the module's existence stays hidden).

The resolved permission map and role name for the current request are available via
`Leap::context()->permissionsFor($module)` and `Leap::context()->roleName()`.

## Two factor authentication

Per-user TOTP with recovery codes, powered by
[Laravel Fortify](https://laravel.com/docs/fortify). Enabled by default
(`leap.auth_2fa.enabled`), so the model **requires** `TwoFactorAuthenticatable`:

```php
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use TwoFactorAuthenticatable;
}
```

The `two_factor_secret`, `two_factor_recovery_codes` and `two_factor_confirmed_at`
columns are added by the package migrations. Users enable, confirm and disable 2FA from
the **Profile** screen. Mandatory enrollment can be toggled in config; when pending,
only the Profile screen is reachable.

## Passkeys

Passwordless login with passkeys (WebAuthn), powered by
[Laravel's passkeys package](https://github.com/laravel/passkeys-server). Enabled by
default (`leap.auth_passkeys.enabled`), so the model **requires** the
`PasskeyAuthenticatable` trait and the `PasskeyUser` contract:

```php
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use PasskeyAuthenticatable;
}
```

Use the `Laravel\Passkeys` namespace directly, **not** the `Laravel\Fortify` wrapper of
the same trait/contract: that wrapper only exists on very recent Fortify releases, so
depending on it breaks on older `^1.19` installs.

The `passkeys` table is added by the package migrations. Users register passkeys from
the **Profile** screen and then sign in with just their device biometrics/PIN — no
password or 2FA challenge involved. A registered passkey satisfies the 2FA requirement.

## Password reset

The forgot/reset password flow is enabled by default (`leap.password_reset`) and uses
Laravel's password broker, so a `password_reset_tokens` table (part of the default
Laravel schema) and a configured mailer are required.
