# Changelog

All notable changes to `nickdekruijk/leap` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.9.5] — 2026-07-10

### Changed

- **Panel CSS rewritten from SCSS to plain CSS, and consolidated from 12 files to 3.**
  `resources/css/*.scss` → `leap.css` (core admin chrome), `filemanager.css`,
  `editor.css`. Colors are now CSS custom properties (`--leap-*`) alongside the
  existing spacing tokens, so host apps re-theme by overriding variables instead of
  overriding selectors — no recompile needed. Shared components like `.leap-button`
  now carry a real default background via `--leap-button-bg`/`--leap-button-bg-hover`
  instead of being re-styled in multiple files per context.
- `AssetController::css()` no longer compiles with ScssPhp — it concatenates the
  (now plain) CSS files directly. `nickdekruijk/minify` (and its transitive
  `scssphp/scssphp`) is no longer a leap-core dependency; it moved to `suggest` and
  is offered/installed only for the scaffolded frontend template, which still uses
  it for its own SCSS/JS.
- The Open Sans `@import url(...)` moved out of the compiled CSS into a `<link>` tag
  in the admin layout `<head>` (native `@import` must precede all other rules, which
  file concatenation no longer guarantees).

### Breaking

- The per-file host CSS override path (`resources/css/leap/<file>.scss`) now expects
  the new filenames (`leap.css`, `filemanager.css`, `editor.css`) — a host overriding
  the old per-feature `.scss` files (e.g. `nav.scss`, `forms.scss`, `login.scss`)
  needs to migrate that override to the consolidated files.
- If `nickdekruijk/minify` was relied upon transitively through `nickdekruijk/leap`
  outside of the template, add it to the host's own `composer.json`.

## [0.9.4] — 2026-07-10

### Fixed

- Test suite only: `HasLocaleRoutingTest` refreshes the router's name lookup after
  registering routes so `route()` resolves them without a preceding request,
  fixing a failure under `--prefer-lowest` (Laravel 12). No shipped code changed
  from 0.9.3.

## [0.9.3] — 2026-07-10

### Added

- **Reusable multilingual routing/SEO building blocks.** The locale-aware
  frontend logic that used to live only in the template stub is now part of the
  package, so projects with content types outside the page tree (e.g. services,
  stories, blogs on their own routes) get the same behaviour without copying it:
  - `Leap::localeDefault()`, `Leap::localePrefix()` and `Leap::detectLocale()` —
    one source of truth for the default locale, the `/xx` URL prefix rule and
    stripping a leading locale segment. The template `PageController` now uses
    these instead of its own private copies (behaviour unchanged).
  - `Middleware\SetLeapLocale` and the `Route::leapLocalized()` macro — register
    a frontend route once and get one group per configured locale, each with the
    right prefix (default locale unprefixed) and the request locale applied per
    request (never at route-registration time). The URL segment can differ per
    locale (e.g. `diensten` in nl, `services` in en).
  - `Traits\HasLocaleRouting` — per-locale URLs (`localeUrls()` / `localeUrl()`)
    and a default `Sitemapable` implementation for a flat translatable model
    whose routes follow the macro's `<name>.<locale>` naming.
- **Pluggable sitemap.** `Contracts\Sitemapable` plus `Classes\Sitemap` and the
  new `leap.sitemap.models` config let any model contribute entries to
  `sitemap.xml`; the helper merges them (skipping missing/non-Sitemapable
  classes). The template's `Page` implements it and the sitemap route falls back
  to a page-tree-only sitemap when no models are configured, so existing sites
  are unaffected.
- **`Section::translatableOnly()` / `translatableExcept()`.** Mark section
  sub-fields translatable in bulk. `translatableOnly('head', 'body')` is the
  explicit, safe form; `translatableExcept()` auto-marks only textual fields
  (text/textarea/rich-text) and skips switches, media, selects, dates, etc.,
  reducing the chance of forgetting a field. Individual `Attribute::translatable()`
  calls are unchanged.
- **`Traits\HasSlug` and `Traits\HasDocumentMeta` moved into the package.** The
  per-locale slug generation and the `documentTitle()` / `ogImageUrl()` head
  metadata are now package traits (fixable via `composer update`). The template
  ships a thin `App\Traits\HasSlug` wrapper so the application namespace is
  stable, and `HasDocumentMeta` degrades gracefully on models without
  media/sections.

## [0.9.2] — 2026-07-10

### Added

- `leap:module` artisan command: generates a resource from an existing Eloquent model,
  detecting field types, required/unique/sortable, foreign keys, enums, `$active` and
  `$orderBy` from the model's schema and casts. Re-running against an existing module
  merges in only the new columns instead of overwriting hand-written attributes.

### Fixed

- Template's `sitemap.xml` is now multilingual: every page gets one `<url>` entry per
  locale it has a routable slug translation for (cascading from its parent chain), each
  with `<xhtml:link>` hreflang alternates — matching the language-switcher already
  rendered in the page head. Monolingual sites are unaffected.

## [0.9.1] — 2026-07-10

### Fixed

- Correct the dependency constraints: require **PHP ^8.3** (runtime deps and the typed
  constants need it) and raise **laravel/fortify to ^1.31**, the floor that has
  `Fortify::currentEncrypter()` used by the 2FA flow.
- Test on Laravel 13 too: widen the dev tooling to Testbench `^10|^11` and PHPUnit
  `^11|^12`, and run the CI matrix as PHP 8.3–8.4 × Laravel 12/13. (PHP 8.2 is dropped —
  runtime deps require 8.3.) Fixed one enrollment test whose expected value only matched
  under PHPUnit 11's loose comparison.

## [0.9.0] — 2026-07-10

Release candidate for 1.0.0, tagged for real-world testing before the stable release. The
public fluent API (`Attribute`, `Section`, `Module`, `Resource`) is stabilising and treated
as frozen; the 1.0.0 tag will make that guarantee binding under semver. As a 0.x release,
semver still allows adjustments if testing surfaces something.

**Stability:** semver covers the module DSL you write — the fluent builders on
`Attribute`/`Section` and the `Module`/`Resource` classes you extend (their properties
and overridable methods). Methods marked `@internal` are Leap's own rendering/plumbing
that happen to be `public` (PHP has no package-private); they are **not** part of the
supported API and may change in a minor release. Don't call them from application code.

### Added

- **Multilingual content editing.** Set `leap.locales` to an associative array
  (e.g. `['nl' => 'Nederlands', 'en' => 'English']`) to edit translatable fields
  per locale in the admin. The editor shows a language switcher in the button bar
  (abbreviated tabs for up to three locales, a dropdown for four or more), a
  per-field locale badge, and
  validates the default locale as required with the others optional. Gated on
  `leap.locales`: when it is `null` (the default) behaviour is byte-for-byte
  identical to before. Mark section sub-fields with `Attribute::translatable()`;
  top-level fields derive translatability from the model's `$translatable`.
  Legacy monolingual values (plain strings from before a field became
  translatable) are wrapped into the default locale on load, so upgrading a
  record preserves its content instead of overwriting it on the first save.
- **AI content assistance.** With an AI provider configured under `leap.ai`
  (Gemini, Claude, OpenAI, or DeepL for translation), the admin can generate
  image **alt texts** per locale in the file manager and **translate** editor
  content into the active locale — per field or all fields at once (including
  section sub-fields), with an empty-only or overwrite scope. HTML markup is
  preserved, slug fields stay slugified, and results fill the form for review
  (nothing is saved automatically). Disabled by default; each task picks its own
  provider and model, and calls are per-user rate-limited and time-bounded. See
  [docs/ai.md](docs/ai.md).
- **Lazy click-to-edit rich-text.** Rich-text fields can show their rendered
  HTML as a preview and only initialize TinyMCE when clicked (torn down again on
  save), so editors with many rich-text sections open fast. Toggled by
  `leap.tinymce.lazy` (top-level fields, default off) and
  `leap.tinymce.lazy_sections` (section fields, default on).
- **`Attribute::slugFrom('source')`.** Declared on the slug field — the slug-field
  form of the slug relationship, mirroring `slugify()` (which declares the same thing
  on the source field). The source field is made live so the slug placeholder updates
  as you type. Works per locale.
- **`Attribute::label()`, `placeholder()` and `hint()` accept a per-locale array**
  (e.g. `->label(['nl' => 'Titel', 'en' => 'Title'])`), resolved to the current
  locale. `hint()` renders as an `(i)` tooltip next to the field label.
- **`Leap::context()` / `LeapContext`** — a request-scoped store for the active
  module, permission map and role name.
- **`leap.cache`** config option (default on). The frontend template caches its
  page tree and invalidates automatically on page save/delete.
- **`leap:template --diff`** reports how a project's template files differ from
  the current stubs without changing anything.
- Frontend template modernised: self-contained `slide`/`default`/`highlights`/
  `cta`/`quote` sections with optional per-section background photos, a carousel,
  a keyboard-accessible horizontal scroller, locale-aware live search (title,
  description and section content matched against the active locale only), an
  admin-editable
  footer, per-page SEO meta (Open Graph, Twitter, canonical, hreflang) and a
  `sitemap.xml`. Bilingual (nl+en) out of the box, per project switchable.
- Template ships `public/css/tinymce.css` and `leap:template` points
  `leap.tinymce.content_css` at it, so rich-text in the editor is styled like the
  frontend (buttons, headings, links). The seeded homepage now demonstrates every
  section layout (all `default` image positions, quote, cta, slider, highlights).
- `App\Traits\HasSlug` for the template: per-locale, sibling-and-locale-unique
  slugs, with `/` reserved for the homepage.
- **Responsive images (frontend template).** Section images and background photos are
  served through `nickdekruijk/imageresize`: `config/imageresize.php` (shipped by
  `leap:template`) defines width presets (600–2560) and the views emit `srcset`/`sizes`;
  full-bleed backgrounds are lazy `<img>` elements. Leap caches each image's intrinsic
  dimensions in `media.meta` via `Media::dimensions()`, so the section `<img>` carries
  `width`/`height` and reserves the correct box (no layout shift, no cropping). Requires
  `php artisan storage:link`.
- **Per-section "dark background" toggle** in the template's `default`/`highlights`/`cta`/
  `quote` sections — white text with the background photo (a legibility overlay) or a
  gradient fallback — plus a text-only image position.

### Changed

- Request-scoped state (active module, permissions, role) moved from Laravel's
  `Context` hidden keys to the scoped `LeapContext` service, so it no longer
  leaks into queued jobs or logs. **Backward compatible:** the old
  `leap.module` / `leap.permissions` / `leap.role.name` Context keys are still
  mirrored throughout 1.x (see Deprecated).
- The frontend template's homepage is the page whose slug is `/`
  (order-independent), and no longer also reachable at `/home`.

### Deprecated

- The `Context` hidden keys `leap.module`, `leap.permissions` and
  `leap.role.name` are mirrored for backward compatibility only and will be
  removed in 2.0. Read them through `Leap::context()` instead.

### Fixed

- Logging no longer writes a `user_id` for a session that points at a user who no
  longer exists (which could hit the `leap_logs` foreign key after a
  `migrate:refresh`). The user is resolved through the auth provider and stored as
  `null` when gone.

### Security

- **File manager uploads are re-validated server-side.** `$uploads` is a public
  (client-controllable) Livewire property, so the extension/size checks in
  `uploadStart` and the target path could be bypassed by setting the array directly
  (`error=false`, a forged name/path) and calling `uploadDone` — writing an
  arbitrary-named file anywhere on the disk with only `create` permission. `uploadDone`
  now re-checks the allow-list and size against the actual file and rebuilds the target
  directory from the open folders.

### Notes on upgrading

- Template/stub changes only apply when you re-run `php artisan leap:template`;
  existing projects are unaffected by `composer update` alone. Use
  `leap:template --diff` first to preview drift.
- Enabling `leap.cache` is safe everywhere because page edits invalidate it;
  disable with `LEAP_CACHE=false` or clear with `php artisan cache:clear`.
- Supported runtimes: PHP 8.3–8.4, Laravel 12/13, Livewire 3/4.

## [0.3.2] and earlier

See the Git history for pre-1.0 changes.
