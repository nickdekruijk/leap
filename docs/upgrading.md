# Upgrading

Release by release, newest first. See [CHANGELOG.md](../CHANGELOG.md) for the full list;
these are the practical notes.

## 1.3 — resized images

Nothing changes on `composer update` alone: `leap.images.enabled` ships as `false`, so no
route is registered, no disk is defined, and no migration runs. Everything below is what
it takes to actually start using it, and to drop `nickdekruijk/imageresize`.

Two fixes do land everywhere, flag or no flag, because both were wrong:
`Media::dimensions()` now applies the EXIF orientation of a photo off a phone or camera
(only for rows that have not cached a size yet, so in practice new uploads), and cropping
over an original now refreshes those cached dimensions instead of leaving the shape of the
picture as it was.

### Turning it on

Add to your published `config/leap.php` — only what differs, the rest arrives from the
package:

```php
'images' => [
    'enabled' => true,
    'widths' => [600, 900, 1200, 1600, 1920, 2560],
],
```

Take `widths` from the site's `config/imageresize.php`: a width that is not configured is
skipped without a word, so a `srcset` asking for one silently loses that candidate. Leave
`route` at `img` while imageresize is still installed — that package claims
`resized/{template}/{image}` with a catch-all segment, and which of the two answers a
given URL would come down to provider order.

Then `/public/img` in `.gitignore`.

### Migrating the views

**This is the part that has to happen before `composer remove`,** because every
`asset_resized()` left in a view is a fatal error the moment the package is gone.

1. Replace the body of `resources/views/components/responsive-image.blade.php` with a
   pass-through to `<x-leap::responsive-image>`, or point the call sites at the leap
   component directly and delete the local one. Either way every
   `<x-responsive-image>` on the site is converted in one edit.
2. Convert what calls the helper directly: `asset_resized($width, $media->file_name)`
   becomes `$media->url($width)`, and `asset_resized($width, $path)` for an image with no
   Media row (a video poster) becomes `Leap::image($path, $width)`.
3. `grep -rn "asset_resized\|imageresize" app resources config` has to come back empty.

### Removing imageresize

```bash
php artisan leap:images --warm     # optional, but the first visitor pays otherwise
composer remove nickdekruijk/imageresize
rm config/imageresize.php
rm -rf public/resized
```

And swap the `.gitignore` line from `/public/resized` to `/public/img`.

### Two things to set up while you are there

- **`leap:images --prune` in the scheduler**, monthly. Nothing is ever overwritten — a
  copy is addressed by the hash of what it was made from — so replaced originals leave
  their old copies behind. Leap takes those along itself when an image is deleted, renamed
  or replaced through the panel; this is for whatever happened out of its sight.
- **A long cache header on `^/img/`** in the web server config. The URL changes whenever
  the file does, so `max-age=31536000, immutable` is safe by construction. See
  [images.md](images.md#deploying).

If the site deploys to a fresh release directory, `public/img` starts empty every time:
either symlink it to shared storage or add `leap:images --warm` as a post-deploy step.

### Worth knowing

- **Imagick is worth switching to** on a site with photographs off a camera. GD decodes
  into PHP's own memory at roughly `width × height × 4` bytes, so a single 48 megapixel
  frame peaks past a 256 MB limit and is refused by the pixel guard — served full size
  instead of resized. Add `config/image.php` with
  `'driver' => Intervention\Image\Drivers\Imagick\Driver::class`. It also unlocks
  `leap.images.defaults.effort`, worth about a tenth of the file size for nothing.
- **`leap.filemanager.upload_replace`** is new and off. The numbering that turns a
  re-uploaded `header.jpg` into `header-1.jpg` exists because a resized copy used to be
  addressed by file name alone, which made replacing a file something that could not be
  seen. The hash in the URL removes that reason; turn this on if "upload a new version"
  should mean what it says. It changes every page using that image at once, which is why
  it is still off by default.
- **`leap:images --sync`** after anything writes to the media disk from outside leap — an
  rsync, a deploy script, a database import. Nothing else re-reads the row every URL is
  built from.
- **Existing sites do not have to re-publish their config.** Leap merges what it ships
  into yours at every level, so the whole `images` block arrives at its documented
  defaults whether or not your file mentions it. `config:cache` is fine: that command
  clears and re-boots before dumping, so the merge is in the cached file.

## Upgrading to 1.0

The 1.0 release is designed to minimise breakage.


### What semver covers from 1.0

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

### Non-breaking by design

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

### Things to be aware of

- **`Context` keys.** The request-scoped state moved from Laravel `Context`
  (`leap.module`, `leap.permissions`, `leap.role.name`) to the `LeapContext` service.
  Those keys are still mirrored throughout 1.x for backward compatibility and will be
  removed in 2.0. If you read them, switch to `Leap::context()`.
- **Mandatory 2FA enrollment** has an explicit default in config — review
  `leap.auth_2fa` if you rely on a specific setting.
- **Your published config no longer has to be complete.** Leap merges the config it
  ships into yours at every level, so a key a release adds below the top level now
  arrives at its documented default rather than as `null`. Mostly this is the fix for a
  silent problem — but it does mean a section you *shortened* to switch something off is
  now filled back in. If you disabled a feature by deleting its key rather than setting
  it, set it explicitly. Lists are never merged, so `default_modules` and friends still
  mean exactly what they say. See
  [configuration.md](configuration.md#what-a-published-config-has-to-contain).
- **Image generation moved to `leap.ai.image.presets`.** A preset names the model (and
  optionally its quality and stored size), and the model name says which provider runs
  it, so `leap.ai.image.provider`, `model` and `quality` are gone from a freshly
  published config. An existing config keeps working unchanged — those three keys still
  behave as a single unnamed preset — but they are **deprecated and will be removed in
  2.0**, so move them into `presets` when convenient. Two presets or more turn the
  generate dialog's model choice into a picker; see
  [ai.md](ai.md#image-presets).
- **Generated images are no longer cropped or scaled down.** The dialog asks for a shape
  (landscape, portrait, square) instead of an exact aspect ratio, and the stored JPEG
  keeps the proportions *and* the resolution the model produced — so a template that
  assumed every generated image was exactly `16:9` should set the ratio in CSS, and a
  site that serves the stored file straight into a page may want to set
  `leap.ai.image.max_width` (freshly published configs now have `null`; an existing
  config keeps whatever number it has, and one that never mentioned the key follows the
  `null` too). `leap.ai.image.aspect_ratios` is no longer read and can be deleted from
  your config.
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
- **TinyMCE `content_css` now needs a `.tinymce` scope.** The default tinymce
  options set `'body_class' => 'tinymce'`, and the click-to-edit preview
  (`lazy_sections`) renders the content inline in the admin page with the same
  `content_css` applied. Element-level rules that used to be safely isolated inside
  the old editor iframe — e.g. `h3 { color: red }` in a project's
  `public/css/tinymce.css` — now leak onto the admin panel's own headings. Scope every
  rule under `.tinymce` (`.tinymce h3 { … }`) and add `'body_class' => 'tinymce'` to
  `leap.tinymce.options` if your config predates it, so the same stylesheet styles both
  the editor body and the preview without bleeding out.
- **`leap.login_image` now defaults to `null`** instead of a random
  `picsum.photos` photo, so a login page no longer calls a third party out of the
  box. Existing configs keep whatever they already have; only a freshly published
  `config/leap.php` gets `null`. The picsum URL stays in the config comment — put
  it back (or point at your own image) to get the photo again.

### Pre-`getPages()` projects

Very old projects scaffolded before the current template (without
`PageController::getPages()`) are not covered by the stub drift mechanism and need to be
re-scaffolded with `php artisan leap:template` (then reconcile with `--diff`).

### Template scaffolding moved to `nickdekruijk/leap-template`

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

### Template: content types (news/events/…)

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
