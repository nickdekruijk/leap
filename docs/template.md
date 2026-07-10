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

## Sections

The template ships self-contained section types — `slide` (carousel), `default`
(text + image; the image is positionable left/right/wide or omitted for text-only, with
an optional dark background — white text plus a background photo or a gradient fallback),
`highlights` (horizontal scroller), `cta` and `quote`. See [sections.md](sections.md).

## Images & responsive assets

Section images and background photos are served through `nickdekruijk/imageresize`. The
width presets in `config/imageresize.php` (600–2560) resize originals on request, and the
Blade views emit `srcset`/`sizes` so the browser picks the right size for the viewport and
device pixel ratio. Backgrounds are lazy `<img>` elements (`object-fit: cover`); the first
slider image is eager for a fast LCP.

Two setup notes:

- **Run `php artisan storage:link`.** The resize originals are read from `/storage`
  (Leap stores uploads on the `public` disk). Without the symlink, images 404.
- The shipped `config/imageresize.php` sets `originals => 'storage'` and the width
  templates the views use; it overrides the package default, which doesn't define them.

Leap caches each image's intrinsic dimensions in `media.meta` (via `Media::dimensions()`),
so the section `<img>` carries `width`/`height` — the browser reserves the correct box
(no layout shift) while the image keeps its natural ratio (no cropping).

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
`hreflang` alternates are rendered in the layout, plus a generated `sitemap.xml`.

## Caching

The page tree is cached per locale and invalidated automatically on page save/delete.
See [caching.md](caching.md).
