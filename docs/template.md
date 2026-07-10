# Frontend template

`php artisan leap:template` scaffolds a complete, semantic-HTML public website into
your project. It is opt-in: nothing is copied until you run the command, and it uses a
sha1 comparison to ask before overwriting files you have changed.

```bash
php artisan leap:template          # install / update the template
php artisan leap:template --diff   # preview how your files differ from the stubs
```

## What it installs

- `app/Http/Controllers/PageController.php` — frontend routing and navigation.
- `app/Models/Page.php` + `app/Leap/Page.php` — the page model and its admin module.
- `app/Traits/HasSections.php` and `app/Traits/HasSlug.php`.
- The pages migration and `PageSeeder` (bilingual sample content).
- `resources/css`, `resources/views`, `resources/js` — SCSS, Blade and JS.
- `config/imageresize.php` — width presets for responsive images (see below).
- `public/css/tinymce.css` — rich-text editor styles that match the frontend.
- Starter feature tests under `tests/Feature`.
- Two routes appended to `routes/web.php`: `sitemap.xml` and the catch-all page route.

It also offers to install the frontend packages the template uses
(`nickdekruijk/settings`, `nickdekruijk/imageresize`, `nickdekruijk/vanilla-slider`,
`nickdekruijk/horizontal-scroller`). These are kept out of leap's own `require` so
existing projects are never forced to pull them.

## Routing

`PageController::getPages()` builds the page tree; `getMenu()` returns the navigation.
Key conventions:

- The **homepage** is the page whose slug is `/` (order-independent); it is not also
  reachable at `/home`.
- Pages with an empty slug in the active locale are not routable.
- When `leap.locales` is set, the default locale is unprefixed and secondary locales
  are prefixed (`/en/...`).

## HasSlug

`HasSlug` generates and persists slugs on save:

- empty slug → `Str::slug(title)` for that locale, falling back to the default-locale
  title so a page is never unreachable;
- unique within `(parent, locale)`, appending `-2`, `-3`, … on collision;
- the slug `/` (homepage) is never slugified.

Declare the editor relationship on the slug field with
`Attribute::make('slug')->slugFrom('title')`.

The behaviour lives in the package (`NickDeKruijk\Leap\Traits\HasSlug`) so fixes arrive
via `composer update`; `leap:template` ships a thin `App\Traits\HasSlug` wrapper around
it, keeping the application namespace stable and giving you a place for project
overrides. Use it on any translatable model with a `slug` field, not just `Page`.

## HasDocumentMeta

`NickDeKruijk\Leap\Traits\HasDocumentMeta` supplies the two `<head>` values the layout
renders, so they are consistent (and fixable) across every routable model:

- `documentTitle()` — a custom `html_title` verbatim, otherwise the page title with the
  app name appended. It reads `html_title` **without** translation fallback, so an empty
  value in the active locale falls through to the page title rather than borrowing
  another locale's.
- `ogImageUrl()` — the model's own image, then its first section image/background, else
  `null` (the layout can fall back to a site-wide `og_image` setting).

It degrades gracefully: it works on any model, uses `HasTranslations` when present, and
only inspects media/sections when the model uses `HasMedia` / `HasSections`.

## Sections

The template ships self-contained section types — `slide` (carousel), `default`
(text + image; the image is positionable left/right/wide or omitted for text-only, with
an optional dark background — white text plus a background photo or a gradient fallback),
`highlights` (horizontal scroller), `cta` and `quote`. See [sections.md](sections.md).

## Images & responsive assets

Section images and background photos are served through `nickdekruijk/imageresize`. The
width presets in `config/imageresize.php` (600–2560) resize originals on request, and the
`<x-responsive-image>` component (see below) emits `srcset`/`sizes` so the browser picks
the right size for the viewport and device pixel ratio. Backgrounds are lazy `<img>`
elements (`object-fit: cover`); the first slider image is eager for a fast LCP.

Two setup notes:

- **Run `php artisan storage:link`.** The resize originals are read from `/storage`
  (Leap stores uploads on the `public` disk). Without the symlink, images 404.
- The shipped `config/imageresize.php` sets `originals => 'storage'` and the width
  templates the views use; it overrides the package default, which doesn't define them.

Leap caches each image's intrinsic dimensions in `media.meta` (via `Media::dimensions()`),
so the section `<img>` carries `width`/`height` — the browser reserves the correct box
(no layout shift) while the image keeps its natural ratio (no cropping).

### `<x-responsive-image>`

A shared component (`resources/views/components/responsive-image.blade.php`)
consolidates the `srcset`/`sizes`/`alt`/dimensions/focus-point boilerplate every
section view needs, instead of each one hand-rolling it:

```blade
<x-responsive-image :media="$image" sizes="(max-width: 550px) 100vw, 50vw" :widths="[600, 900, 1200, 1600]" fallback="900" />
```

- `media` — a `Media` instance (e.g. `$section->image->first()`).
- `sizes` — **required**, the CSS `sizes` value for how the image is actually laid
  out. There is no sensible universal default: `"100vw"` for a full-bleed background,
  `"(max-width: 550px) 100vw, 50vw"` for a half-width content image, a fixed px value
  for a small thumbnail.
- `widths` — the `asset_resized()` breakpoints to generate (default `[600, 900, 1200,
  1600]`); the `w` descriptor is the real pixel width of each generated file.
- `fallback` — the width used for the plain `src` (browsers without `srcset` support);
  defaults to the middle of `widths`.
- `eager` — `fetchpriority="high"` instead of `loading="lazy"`, for an LCP-critical
  image (e.g. the first slide).
- `decorative` — forces `alt=""` for a background/decorative image instead of
  `$media->alt()`.

`width`/`height` come from `Media::dimensions()` automatically when available. When the
media has a **crop focus point** set in the file manager (`Media::focusPosition()`), the
component emits `object-position: {x}% {y}%` inline so it stays visible under
`object-fit: cover` — sections that don't set a focus point keep the CSS default
(`object-position: center`).

WebP/AVIF are not generated yet (a possible future addition).

## Assets: no npm / Vite

SCSS is compiled on request by `nickdekruijk/minify` (ScssPhp) and JS is served the
same way. **There is no build step.** Edit the files under `resources/css` and
`resources/js` and reload. Two SCSS files are meant to be edited:

- `template.scss` — the framework (layout, nav, sections, a11y). Rarely touched.
- `project.scss` — your design tokens (`--accent`, fonts, spacing) and overrides. The
  only file most projects change.

## SEO

Per-page `<title>`, meta description, canonical, Open Graph, Twitter Card and
`hreflang` alternates are rendered in the layout (`<title>` and OG image via
[`HasDocumentMeta`](#hasdocumentmeta), hreflang via the per-locale URL map — see
[multilingual.md](multilingual.md#routing--urls)), plus a generated `sitemap.xml`.
When multilingual, the sitemap lists one `<url>` per record per locale it has a
routable slug translation for, each with `<xhtml:link>` hreflang alternates to
its sibling locale URLs — mirroring the language-switcher links in the layout.

### Pluggable sitemap

The sitemap is not limited to the page tree. Any model that implements
`NickDeKruijk\Leap\Contracts\Sitemapable` can contribute entries; list the models in
`config('leap.sitemap.models')` and the `Sitemap` helper merges them:

```php
// config/leap.php
'sitemap' => ['models' => [
    App\Models\Page::class,
    App\Models\Service::class,
]],
```

`Page` implements `Sitemapable` out of the box. A model that uses
[`HasLocaleRouting`](multilingual.md#haslocalerouting) gets a default implementation for
free — just add `implements Sitemapable`. Missing or non-`Sitemapable` classes in the
config are skipped. When the config is empty the sitemap route falls back to a
page-tree-only sitemap, so existing sites are unaffected.

## Caching

The page tree is cached per locale and invalidated automatically on page save/delete.
See [caching.md](caching.md).
