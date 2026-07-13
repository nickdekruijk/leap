# Changelog

All notable changes to `nickdekruijk/leap` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Shrinking navigation bar in the frontend template** — the sticky bar now animates
  from `--nav-height` down to `--nav-height-compact` as soon as the page scrolls, and
  starts out compact on mobile, where there is no room for the tall state. A tall
  header reads well on arrival but wastes vertical space while reading. It reuses the
  `.scrolling` class Alpine already sets, so no new JavaScript. Both a text logo
  (`--logo-font-size` / `--logo-font-size-compact`) and an `<img>` logo
  (`--logo-height` / `--logo-height-compact`) shrink along with it; duration is
  `--nav-shrink-duration`. Unset the `*-compact` tokens for a bar of fixed height.

### Fixed

- **`leap:user` did not work non-interactively at all** (`--no-interaction`, CI, a
  provisioning script). It leaned on prompts that cannot be asked:
  - Without an e-mail argument it crashed with Prompts'
    `NonInteractiveValidationException` instead of saying what was missing.
  - With one, the password prompt came back blank, so it fell back to a randomly
    generated password — and never printed it. The account was created and immediately
    unreachable, since nothing stores that password in the clear.

  The command now prompts only when it is actually running interactively, always shows a
  generated password, and falls back to the e-mail's name part when no name is given. It
  also warns when the new user ends up without a role (the role prompt defaults to "no",
  leaving an account that sees nothing in the admin panel), and no longer crashes when no
  roles exist yet. The command had no tests; it has seven now.
- **`leap:module` generated a module PHP could not load.** The resource normally
  carries its model's basename (`App\Leap\Project` for `App\Models\Project`), and the
  generated file imported the model — colliding with the class it was declaring:
  *"Cannot redeclare class App\Leap\Project (previously declared as local import)"*.
  It also emitted `public $model = App\Models\Project::class` without a leading
  backslash, which resolves relative to `App\Leap`. The model is now referenced fully
  qualified and never imported. The command was effectively unusable for any model
  whose name is used as-is, i.e. the default. The existing test only asserted on the
  generated *source text*, so it never noticed; it now lints and loads the file.
- **In-page anchors no longer land under the navigation bar** in the frontend template:
  `scroll-margin-top` now uses the compact height, since a jump to an anchor always
  happens with the bar already shrunk.
- **The logo no longer disappears behind the open mobile menu.** `.nav-main-container`
  is a fixed panel pinned to the top of the viewport, so it covered the whole bar; the
  hamburger lifted itself above it but the logo did not. Longstanding, unrelated to the
  shrinking bar.

## [0.9.11] — 2026-07-12

### Added

- **`leap-development` Boost skill** (`resources/boost/skills/leap-development/SKILL.md`)
  — on-demand agent guidance covering resources/modules, the `Attribute` API, roles
  and permissions, multilingual editing, sections, the frontend template and AI
  features, with pointers into the package's `docs/` directory. Complements the
  existing always-on `resources/boost/guidelines/leap.blade.php`.

## [0.9.10] — 2026-07-12

### Fixed

- **`composer require nickdekruijk/leap` failed without `-W`.** `brick/math`
  wasn't a direct dependency, so on projects where it was already locked to a
  version newer than `spomky-labs/cbor-php` (pulled in via
  `laravel/passkeys` → `web-auth/webauthn-lib`) supports, Composer's partial
  update refused to touch it and the install failed. Declaring `brick/math`
  directly, capped to the range `cbor-php` accepts, puts it in the update
  whitelist so a plain `composer require` resolves it correctly.

## [0.9.9] — 2026-07-10

### Fixed

- **Disabled translate badge no longer hints at an interaction it doesn't have.**
  When AI translate has no provider/key configured, the per-field locale badge
  (e.g. `NL`) correctly went non-clickable, but still showed the `.leap-hint`
  hover color and the global `.leap :focus` blue outline ring — both borrowed
  from the enabled/clickable variant. Now only the tooltip reacts to
  hover/focus, matching the badge's actual (non-interactive) state.
- **`<x-responsive-image>` crashed on SVG media.** `asset_resized()` has no
  decode path for SVG (only bitmap formats); the component now serves SVGs as
  a plain `<img src>` (they're already infinitely scalable, no responsive
  breakpoints needed), branching on `Media::isBitmap()`.

## [0.9.8] — 2026-07-10

### Added

- **`Media::focusPosition()`** — the crop focus point set in the file manager
  (`meta['image_focus']`), as CSS-ready `{x, y}` percentages, or `null` when unset.
  Mirrors `Media::alt()`. Pairs with `object-fit: cover` and inline
  `object-position` to keep the focus point visible when an image is cropped by
  its container's aspect ratio.
- **`<x-responsive-image>` template component**
  (`resources/views/components/responsive-image.blade.php`). Consolidates the
  `srcset`/`sizes`/`alt`/dimensions/focus-point boilerplate that was duplicated
  across the section views (`default`, `slide`, `highlights`) into one component;
  those views now use it. Uses `Media::alt()` and the new `focusPosition()`
  automatically — a focus point set in the admin now actually shows up on the
  frontend, which no section view previously read. See
  [docs/template.md](docs/template.md#x-responsive-image).

## [0.9.7] — 2026-07-10

### Changed

- **Filemanager: rename and alt-text moved into the always-visible button bar.**
  Rename was a small pencil icon next to a deceptively-clickable filename; alt-text
  was only reachable by hovering the image. Both are now `Rename file` / `Set alt
  text` buttons in the top bar next to Close/Delete (single file selected only).
  Focus-point and crop stay on the image itself — they're inherently "click a point
  on the image" actions. The filename in the stats panel is now plain text.
- **`leap.filemanager.image_crop_enabled` / `image_focus_enabled` accept `true`** as
  shorthand for "every bitmap format" (via the existing `isBitmap()` helper, which
  already excludes `svg`), enabled by default. The array form still works for finer
  control — e.g. excluding `gif` from crop (breaks animation) while keeping it for
  focus point.
- Added `:focus-within` alongside `:hover` on `.leap-focus-actions` so the
  focus-point/crop overlay buttons are visible to keyboard users tabbing onto them,
  not just mouse hover.

### Fixed

- **Filemanager: selected folder/file row lost its teal highlight**, rendering as
  near-invisible white-on-white text instead. Regression from the 0.9.5 CSS
  consolidation: `filemanager.css` (loaded last) unconditionally set
  `.leap-index-row TD { background-color: transparent }`, which tied in specificity
  with `leap.css`'s `.leap-index-row-selected TD` rule and won on source order,
  cancelling the selected-row background while `color: white` still applied.
  Scoped the transparent override to `.leap-index-row:not(.leap-index-row-selected)`
  so the two rules no longer compete regardless of file load order.

## [0.9.6] — 2026-07-10

### Changed

- **`HasSlug` now works on flat (non-tree) models.** Slug uniqueness was always
  scoped to a `parent` column, which threw on models without one. It is now scoped
  to siblings only when a sibling column exists — auto-detected as `parent` via the
  new `slugSiblingColumn()` (override to use a different column, or return `null`
  for global uniqueness). Page trees are unchanged; standalone models (services,
  stories, blog posts) can now use `HasSlug` for per-locale slug generation too.

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
