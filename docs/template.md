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
(text + image, positionable, optional background photo), `highlights` (horizontal
scroller), `cta` and `quote`. See [sections.md](sections.md).

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
