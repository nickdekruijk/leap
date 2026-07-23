# Upgrading to 1.0

The 1.0 release is designed to minimise breakage. See [CHANGELOG.md](../CHANGELOG.md)
for the full list; the practical notes:

## What semver covers from 1.0

The module DSL you write against: the fluent builders on `Attribute` and `Section`, and
the `Module`/`Resource` classes you extend (their properties and overridable methods).
Alongside those, three things a project depends on without calling any PHP:

- **The consent banner's markup, class names and `window.consent`** — projects style the
  banner from their own stylesheet and gate their own scripts on that object.
- **`resources/js/consent.js`** as a path. The frontend template bundles it out of the
  package by `base_path('vendor/nickdekruijk/leap/resources/js/consent.js')`, so moving
  the file breaks every generated site with nothing to catch it.
- **Published view names** under `leap::`, which a project can override with
  `vendor:publish --tag=leap-views`.

Methods marked `@internal` are Leap's own rendering and plumbing that happen to be
`public` (PHP has no package-private). They are not part of the supported API and may
change in a minor release — don't call them from application code.

## Non-breaking by design

- **Runtimes:** PHP 8.3–8.5, Laravel 12/13, Livewire 3/4.
- **Multilingual is opt-in:** with `leap.locales` at its default `null`, editor and
  storage behaviour is byte-for-byte identical to before.
- **New `Attribute` methods are additive.** `slugFrom()` adds a slug-field way to
  declare the slug relationship; `slugify()` (on the source field) keeps working as the
  equivalent from the other end.
- **Multilingual routing/SEO building blocks are additive and opt-in.** The
  `Route::leapLocalized()` macro, `HasLocaleRouting`, `Sitemapable` +
  `leap.sitemap.models`, `Section::translatableExcept()`/`translatableOnly()`,
  `HasDocumentMeta` and the package `HasSlug` (behind the existing `App\Traits\HasSlug`
  wrapper) are all new; nothing changes until you use them. See
  [multilingual.md](multilingual.md#routing--urls) and [template.md](template.md).
- **Template/stub changes only apply when you re-run `php artisan leap:template`.** Your
  live site is untouched by `composer update` alone. Run `leap:template --diff` first to
  see what changed.

## Things to be aware of

- **`Context` keys.** The request-scoped state moved from Laravel `Context`
  (`leap.module`, `leap.permissions`, `leap.role.name`) to the `LeapContext` service.
  Those keys are still mirrored throughout 1.x for backward compatibility and will be
  removed in 2.0. If you read them, switch to `Leap::context()`.
- **Mandatory 2FA enrollment** has an explicit default in config — review
  `leap.auth_2fa` if you rely on a specific setting.
- **Panel CSS is now plain CSS, not SCSS, and consolidated from 12 files to 3**
  (`leap.css`, `filemanager.css`, `editor.css`). If you overrode one of the old
  per-feature files under `resources/css/leap/` (e.g. `nav.scss`, `forms.scss`,
  `login.scss`), migrate that override to the new files — see
  [Theming](configuration.md#theming). Prefer overriding the new `--leap-*` CSS
  custom properties instead of a whole file where you can; no recompile needed.
  `nickdekruijk/minify` is no longer a leap-core dependency.
- **`leap.filemanager.image_crop_enabled`/`image_focus_enabled` now default to
  `true`** (every bitmap format) instead of `false`. Only affects a freshly
  published `config/leap.php` — existing configs with an explicit `false` or array
  are untouched. `true` is now valid syntax alongside the existing array form.
- **`leap.login_image` now defaults to `null`** instead of a random
  `picsum.photos` photo, so a login page no longer calls a third party out of the
  box. Existing configs keep whatever they already have; only a freshly published
  `config/leap.php` gets `null`. The picsum URL stays in the config comment — put
  it back (or point at your own image) to get the photo again.

## Pre-`getPages()` projects

Very old projects scaffolded before the current template (without
`PageController::getPages()`) are not covered by the stub drift mechanism and need to be
re-scaffolded with `php artisan leap:template` (then reconcile with `--diff`).

## Template scaffolding moved to `nickdekruijk/leap-template`

**`leap:template` and `leap:content` now ship in a separate dev-only package.** After
upgrading, those commands are gone from a plain `nickdekruijk/leap` install; add the
package to get them back:

```bash
composer require --dev nickdekruijk/leap-template
```

`leap:module` and `leap:user` stay in the core package. On production
(`composer install --no-dev`) `leap-template` is absent, so the scaffolding leaves no
footprint. Both `leap:content` and `leap:module` now also refuse to run on
`APP_ENV=production` without `--force`. See
[nickdekruijk/leap-template](https://github.com/nickdekruijk/leap-template).

## Template: content types (news/events/…)

The frontend template gained model-backed [content types](content-types.md) and dropped
a few things. Re-run `php artisan leap:template` (use `--diff` first) and reconcile:

- **`highlights` section removed.** It was demo-only. Its card row is replaced by the
  registered content types. A project still using a `highlights` section on a page should
  move that content into a real content type, or keep its own `sections/highlights.blade.php`.
- **Page-tree cache removed.** `config('leap.cache')`, `PageController::flushPageCache()`
  and the `Page` model's cache-flush events are gone — `getPages()` is memoized per
  request with `once()`. Remove `LEAP_CACHE` from your `.env` (it is a no-op now).
- **`leap.content` is the new registry.** `sitemap.xml` and live search read it; you no
  longer list content models in `leap.sitemap.models` (kept only for models outside the
  registry). `leap:content` maintains it.
- **New shared files:** `app/Traits/HasTags.php` and `app/Leap/Concerns/ContentSections.php`
  (the Page resource now uses the concern instead of inlining its section blocks).
