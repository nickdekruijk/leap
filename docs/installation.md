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
module, or use `php artisan leap:user` (below) to create one from the command line.

## Artisan commands

| Command | What it does |
|---|---|
| `php artisan leap:user {username?} {name?} {--role[=NAME]}` | Create or update a user. Prompts for whatever isn't passed as an argument (the username column defaults to `email`, per `leap.credentials`). Leave the password prompt blank to get a random one printed to the console. Updating an existing user only touches the name/password you provide. If the user has no role yet, it offers to attach the first available one; `--role` attaches one without asking (bare for the first role, or `--role=superuser` / `--role=1` by name or id), which is what a scripted or `--no-interaction` run needs — without a role the panel 403s. |
| `php artisan leap:module <Model>` | Generate (or update) an `App\Leap\<Model>` resource from an Eloquent model's schema — field types, required/unique, labels, icon and more, auto-detected. See [modules-and-resources.md](modules-and-resources.md#generating-a-resource-leapmodule). |

Run any command with `--help` for its full list of arguments and options.

### Frontend template scaffolding (separate dev package)

The `leap:template` and `leap:content` commands — which scaffold a ready-made public
website and its listed content types — ship in the **dev-only**
[`nickdekruijk/leap-template`](https://github.com/nickdekruijk/leap-template) package, so
they leave no footprint on production:

```bash
composer require --dev nickdekruijk/leap-template
```

See [template.md](template.md) and [content-types.md](content-types.md).
