# Changelog

All notable changes to `nickdekruijk/leap` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] — 1.0.0

The first stable release. The public fluent API (`Attribute`, `Section`, `Module`,
`Resource`) is now frozen; from 1.0 onwards it follows semantic versioning strictly.

**Stability:** semver covers the module DSL you write — the fluent builders on
`Attribute`/`Section` and the `Module`/`Resource` classes you extend (their properties
and overridable methods). Methods marked `@internal` are Leap's own rendering/plumbing
that happen to be `public` (PHP has no package-private); they are **not** part of the
supported API and may change in a minor release. Don't call them from application code.

### Added

- **Multilingual content editing.** Set `leap.locales` to an associative array
  (e.g. `['nl' => 'Nederlands', 'en' => 'English']`) to edit translatable fields
  per locale in the admin. The editor shows a language switcher in the button bar
  (tabs for two locales, a dropdown for more), a per-field locale badge, and
  validates the default locale as required with the others optional. Gated on
  `leap.locales`: when it is `null` (the default) behaviour is byte-for-byte
  identical to before. Mark section sub-fields with `Attribute::translatable()`;
  top-level fields derive translatability from the model's `$translatable`.
  Legacy monolingual values (plain strings from before a field became
  translatable) are wrapped into the default locale on load, so upgrading a
  record preserves its content instead of overwriting it on the first save.
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
- Supported runtimes are unchanged: PHP 8.2–8.4, Laravel 12/13, Livewire 3/4.

## [0.3.2] and earlier

See the Git history for pre-1.0 changes.
