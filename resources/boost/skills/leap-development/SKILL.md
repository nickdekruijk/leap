---
name: leap-development
description: 'ACTIVATE when the user works with Leap (nickdekruijk/leap), a Laravel admin panel package. This includes creating or editing Resource classes (extends NickDeKruijk\Leap\Resource), declaring Attribute::make() fields, the `php artisan leap:module` command, the admin panel at /admin, roles and permissions (HasRoles trait, leap_roles), media/file manager and image uploads, repeatable JSON sections, multilingual/translatable fields, the frontend template (php artisan leap:template), Leap AI features (alt text, translation), or config/leap.php. Also activate when the user mentions Leap modules, resources, or references app/Leap/, NickDeKruijk\Leap\*, or vendor/nickdekruijk/leap. Do NOT activate for Laravel Fortify/passkey internals themselves (use fortify-development for that) unless the question is specifically about how Leap wires them up.'
license: MIT
metadata:
  author: nickdekruijk
---

# Leap — Laravel Easy Admin Panel

Leap gives a Laravel app a full admin panel with almost no boilerplate. Admin screens
("modules") are declared in PHP with a fluent `Attribute` API — no per-screen Blade or
JavaScript. Built on Livewire; styling compiles on request (no npm/Vite build step for
the panel itself). Auth (2FA, passkeys, password reset) is powered by Laravel Fortify.

## Documentation

Full docs ship with the package at `vendor/nickdekruijk/leap/docs/`:

- `installation.md` — requirements, user-model traits, migrations
- `modules-and-resources.md` — `Resource` class, `php artisan leap:module`, list/editor wiring
- `attributes.md` — the `Attribute::make()` fluent API (validation, search, sort, filter, CSV)
- `permissions-and-auth.md` — roles, `HasRoles`, 2FA, passkeys, password reset
- `multilingual.md` — per-locale content, locale-aware routing, `hreflang`/sitemap
- `sections.md` — repeatable JSON section blocks
- `configuration.md` — `config/leap.php` options
- `caching.md` — what Leap caches and how to bust it
- `template.md` — the optional public-site frontend scaffold (`php artisan leap:template`)
- `ai.md` — AI alt-text/translation (`AiTask`, provider config under `leap.ai`)
- `upgrading.md` — breaking changes between versions

Read the relevant doc before making non-trivial changes — don't guess at the
`Attribute` API surface.

## Usage

- **Resource modules** live in `app/Leap/`, extend `NickDeKruijk\Leap\Resource`, and
  declare fields in an `attributes(): array` method using `Attribute::make('field')`.
- Generate a module from an existing model: `php artisan leap:module {Model}`.
- The authenticatable model **must** use `NickDeKruijk\Leap\Traits\HasRoles` — without
  it, permission checks throw `Call to undefined method`.
- Permissions are per-module (`read`/`create`/`update`/`delete`, or
  `all_permissions`/`all_modules`); a user without `read` gets a 404, not a 403 (module
  existence stays hidden). Check via `Leap::context()->permissionsFor($module)`.
- Translatable fields and the multilingual editor are opt-in — see `multilingual.md`
  before assuming a field is per-locale.
- AI alt-text/translation are opt-in and disabled unless a provider + API key are
  configured under `leap.ai` in `config/leap.php`; both only fill the form for review,
  never write to the database on their own.
- Config is published with
  `php artisan vendor:publish --provider="NickDeKruijk\Leap\ServiceProvider" --tag=config`.

## Verification

Leap ships Pest tests under its own `tests/` — when changing Leap internals, run
`vendor/bin/pest` inside the package. When consuming Leap in an app, prefer feature
tests over tinkering against the live `/admin` panel.
