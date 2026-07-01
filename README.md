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

### Two factor authentication

Leap supports per-user two factor authentication (TOTP) with recovery codes,
powered by [Laravel Fortify](https://laravel.com/docs/fortify). Add the
`TwoFactorAuthenticatable` trait to your authenticatable model:

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
disable two factor authentication from the **Two Factor Authentication** screen
in the panel. Disable the feature entirely with `leap.auth_2fa.enabled`.

### Password reset

The forgot/reset password flow is enabled by default (`leap.password_reset`) and
uses Laravel's password broker, so a `password_reset_tokens` table (part of the
default Laravel schema) and a configured mailer are required.
