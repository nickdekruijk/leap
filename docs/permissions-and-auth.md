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
`all_permissions` / `all_modules` wildcards. A user with several roles gets the union: an
ability is granted when any accepted role grants it. A user without `read` on a module
does not see it and receives a 404 (so the module's existence stays hidden).

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
the same trait/contract: that wrapper only arrived in Fortify `^1.37`, and Leap allows
`^1.31`, so depending on it breaks on any install still on 1.31–1.36.

The `passkeys` table is added by the package migrations. Users register passkeys from
the **Profile** screen and then sign in with just their device biometrics/PIN — no
password or 2FA challenge involved. A registered passkey satisfies the 2FA requirement.

Adding or removing a passkey first asks for the current password (Laravel's
`password.confirm` middleware, satisfied by the confirm step on the Profile screen for
`auth.password_timeout`, three hours by default). The passkey routes are throttled
(`passkeys.throttle`, `throttle:6,1` unless Fortify configures a limiter).

## Password reset

The forgot/reset password flow is enabled by default (`leap.password_reset`) and uses
Laravel's password broker, so a `password_reset_tokens` table (part of the default
Laravel schema) and a configured mailer are required.

## Address allowlist

`leap.allowed_ips` limits who can reach the panel at all. Set `LEAP_ALLOWED_IPS` to a
comma separated list of IPs and CIDR ranges (`203.0.113.4,198.51.100.0/24`); any other
address gets a 404 on every panel route, the login screen and the panel's Livewire
updates included, so the panel does not reveal itself. Leave it empty for no
restriction. Behind a proxy or load balancer configure Laravel's trusted proxies first,
otherwise the address checked is the proxy's.
