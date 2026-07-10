# Upgrading to 1.0

The 1.0 release is designed to minimise breakage. See [CHANGELOG.md](../CHANGELOG.md)
for the full list; the practical notes:

## Non-breaking by design

- **Runtimes:** PHP 8.3–8.4, Laravel 12/13, Livewire 3/4.
- **Multilingual is opt-in:** with `leap.locales` at its default `null`, editor and
  storage behaviour is byte-for-byte identical to before.
- **New `Attribute` methods are additive.** `slugFrom()` adds a slug-field way to
  declare the slug relationship; `slugify()` (on the source field) keeps working as the
  equivalent from the other end.
- **Template/stub changes only apply when you re-run `php artisan leap:template`.** Your
  live site is untouched by `composer update` alone. Run `leap:template --diff` first to
  see what changed.

## Things to be aware of

- **`Context` keys.** The request-scoped state moved from Laravel `Context`
  (`leap.module`, `leap.permissions`, `leap.role.name`) to the `LeapContext` service.
  Those keys are still mirrored throughout 1.x for backward compatibility and will be
  removed in 2.0. If you read them, switch to `Leap::context()`.
- **`leap.cache` defaults to on.** The frontend page tree is now cached; it invalidates
  automatically on page save/delete, so this is safe. Disable with `LEAP_CACHE=false`
  or clear with `php artisan cache:clear`.
- **Mandatory 2FA enrollment** has an explicit default in config — review
  `leap.auth_2fa` if you rely on a specific setting.

## Pre-`getPages()` projects

Very old projects scaffolded before the current template (without
`PageController::getPages()`) are not covered by the stub drift mechanism and need to be
re-scaffolded with `php artisan leap:template` (then reconcile with `--diff`).
