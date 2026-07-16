# Frontend template

> **`leap:template` and `leap:content` live in a separate dev-only package.** Install it
> alongside leap: `composer require --dev nickdekruijk/leap-template` (leap itself stays a
> normal, non-dev requirement). On production (`--no-dev`) the scaffolding is absent — see
> [nickdekruijk/leap-template](https://github.com/nickdekruijk/leap-template).

`php artisan leap:template` scaffolds a complete, semantic-HTML public website into
your project. It is opt-in: nothing is copied until you run the command, and it uses a
sha1 comparison to ask before overwriting files you have changed.

```bash
php artisan leap:template          # install / update the template (interactive)
php artisan leap:template --diff    # preview how your files differ from the stubs
php artisan leap:template --fresh   # complete, unattended install (implies --force)
```

On install it asks which **content types** to scaffold (default `News,Event`) and which
**languages** to enable (default Dutch only). Steer both non-interactively:

```bash
php artisan leap:template --fresh --models=News,Project,Product --locales=nl,en
php artisan leap:template --fresh --no-events --no-tags   # skip events and the tag filter
```

- `--models=` — comma list, `Name`, `Name:archetype` or `Name:archetype:plural`; empty =
  no content types. Each is generated with [`leap:content`](content-types.md).
- `--locales=` — comma list of locale codes; one = monolingual. Omitted under `--fresh` =
  Dutch only.
- `--tags` / `--no-tags` — the shared tag filter on content types (default on).

See [content-types.md](content-types.md) for News/Event/generic archetypes, the
`leap.content` registry, overviews, tags and events.

## What it installs

- `app/Http/Controllers/PageController.php` — frontend routing and navigation.
- `app/Models/Page.php` + `app/Leap/Page.php` — the page model and its admin module.
- `app/Traits/HasTags.php` — the shared Tag relation (omitted with `--no-tags`).
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

The trait lives in this package (`NickDeKruijk\Leap\Traits\HasSlug`), and the scaffolded
models use it straight from there, so fixes arrive via `composer update`. Use it on any
translatable model with a `slug` field, not just `Page`. To change its behaviour for one
model, define the method on that model; to change it everywhere, wrap it in a trait of
your own and use that instead.

## HasDocumentMeta

`NickDeKruijk\Leap\Traits\HasDocumentMeta` supplies the `<head>` values the layout
renders, so they are consistent (and fixable) across every routable model:

- `documentTitle()` — a custom `html_title` verbatim, otherwise the page title with the
  app name appended. It reads `html_title` **without** translation fallback, so an empty
  value in the active locale falls through to the page title rather than borrowing
  another locale's.
- `metaDescription()` — the model's `description`, falling back to the `intro` that a
  listed content item already carries as its card text, else `''` (the layout can then
  skip the tag). Read without translation fallback, like `documentTitle()`. A page has
  no intro and simply gets its description.
- `ogImageUrl()` — the model's own image, then its first section image/background, else
  `null` (the layout can fall back to a site-wide `og_image` setting).

It degrades gracefully: it works on any model, uses `HasTranslations` when present, and
only inspects media/sections when the model uses `HasMedia` / `HasSections`. An
attribute a translatable model does not list in `$translatable` is read as a plain
attribute rather than throwing — which is what lets `metaDescription()` reach for an
`intro` on models that have none.

## Sections

The template ships self-contained section types — `slide` (carousel), `default`
(text + image; the image is positionable left/right/wide or omitted for text-only, with
an optional dark background — white text plus a background photo or a gradient fallback),
`cta`, `quote` and `video`. Each registered [content type](content-types.md) also adds a
card-row section (a teaser or a full overview). See [sections.md](sections.md).

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

The compiled result lands in `public/css/builds` and `public/js/builds`. That is build
output, not source: `leap:template` adds both to `.gitignore`, and they regenerate on the
first request, directories and all. Committing them means every branch carries a rebuilt
artifact that conflicts on merge, and a stale copy can mask a broken source.

## Navigation

The bar is sticky. Alpine adds `.scrolling` to it as soon as the page leaves the top,
which draws a shadow and **shrinks the bar** — a tall header is welcome on arrival but
wastes vertical space while reading. On mobile the bar starts out compact instead,
since there is no room for the tall state. Menu items stay vertically centred
throughout, and in-page anchors offset by the compact height (a jump to an anchor
always happens with the bar already shrunk).

All of it is driven by tokens in `project.scss`:

| Token | Purpose |
|---|---|
| `--nav-height` | Height at the top of the page |
| `--nav-height-compact` | Height once scrolled, and the height on mobile |
| `--logo-font-size` / `--logo-font-size-compact` | Sizes a **text** logo |
| `--logo-height` / `--logo-height-compact` | Sizes an `<img>` logo |
| `--nav-shrink-duration` | Animation duration (default `0.25s`) |

Leave the `*-compact` tokens unset for a bar of fixed height. The animation is
suppressed under `prefers-reduced-motion`.

## SEO

Per-page `<title>`, meta description, canonical, Open Graph, Twitter Card and
`hreflang` alternates are rendered in the layout (`<title>` and OG image via
[`HasDocumentMeta`](#hasdocumentmeta), hreflang via the per-locale URL map — see
[multilingual.md](multilingual.md#routing--urls)), plus a generated `sitemap.xml`.
When multilingual, the sitemap lists one `<url>` per record per locale it has a
routable slug translation for, each with `<xhtml:link>` hreflang alternates to
its sibling locale URLs — mirroring the language-switcher links in the layout.

### Pluggable sitemap

The template's `sitemap.xml` covers the page tree **and every registered
[content type](content-types.md)** automatically (each item under its overview page,
one URL per locale). Nothing to configure — `config('leap.content')` is the source.

For models outside that registry, `config('leap.sitemap.models')` still merges any
`NickDeKruijk\Leap\Contracts\Sitemapable` model via the `Sitemap` helper:

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

## Cookie consent

Configured under `leap.consent`. The banner, the cookie table, the CSS and the JS all
ship **with the package**, not as template stubs — a fix to something that has to hold up
legally should reach every site through `composer update`, not leave each one on its own
frozen copy.

### The registry is a manifest, not decoration

`leap.consent.categories` declares, per category, which services the site uses and which
cookies each one sets — with **purpose, retention and provider**. That has to be written
by hand: a scanner can see that a cookie exists, but never what it is *for* or how long
it is kept, and those are precisely the things a privacy statement must state.

Two things then keep it honest:

- `@include('leap::cookie-table')` renders the registry on the privacy page, so the page
  cannot drift away from the code.
- A browser test measures the real site against it. **A cookie that turns up without
  being declared fails the build** — an integration cannot quietly start setting cookies
  and turn the privacy page into a lie. (Cookies set by the server are httpOnly and
  invisible to script, so they are checked from their `Set-Cookie` headers; cookies set
  by JavaScript appear in no header at all, so those need a real browser. Both halves are
  needed.)

Add a service and the registry's fingerprint changes, which **expires the consent already
given**: it covered what was on the table at the time, and no longer does.

### Nothing loads before it is allowed

Pages are cached server-side, so the HTML is identical for every visitor and can never
contain a tracker or an `<iframe>`. Anything that needs permission is parked in a
`<template data-consent="analytics">`, which the browser parses but does not run — no
script executes, no request goes out, not even for an external `src`. `consent.js` clones
it into the page once that category is granted, recreating the `<script>` elements so
they actually run.

That is why the template needs to know nothing about GA4, Meta or Hotjar: an editor pastes
the vendor's own snippet into the `scripts_<category>` setting and it works unchanged.
`html_head` renders before `</head>` and is for code that needs **no** consent — a tracker
in there runs outside the consent system entirely.

> ⚠️ `html_head` and `scripts_*` are executed unescaped. That is an XSS hole for anyone
> who can reach the settings module; restrict it to the superuser role.

### Matomo

Supported directly (`leap.consent.matomo`) because its cookieless mode is genuinely worth
having: with `requireCookieConsent` it measures **every** visitor without setting a cookie,
so the cookie law is never triggered and the people who refuse still show up in the
figures. Consent only switches its cookies on, for better numbers. Nothing else can do
this — GA4 and the rest belong behind consent in a `scripts_*` slot.

This is not a free pass: cookieless Matomo also needs IP anonymisation on, no sharing with
third parties, and a processing agreement. Those are settings in Matomo and paperwork, not
code.

### The banner is a bar, never a wall

No backdrop, no focus trap, no scroll lock: a visitor who ignores it can read and use the
entire site, and nothing optional loads until they say so. Refusing is one click, exactly
like accepting, and nothing is pre-ticked.

This is not politeness. A banner that holds the content hostage until someone chooses is a
cookie wall, and consent given to be rid of a barrier is not freely given — which makes it
worthless, and the site no better off than with no banner at all.

### Switches

| Key | |
|---|---|
| `enabled` | `false` = no banner at all. Every category falls back to `default`. |
| `default` | What a category is worth when nobody was asked: `denied`, or `granted` to knowingly skip the question (not GDPR-proof — a deliberate choice). |
| `granular` | `true` = a preferences screen per category. `false` = accept all / refuse. All-or-nothing is fine with **one** optional category — a screen with a single switch is theatre — but with several distinct purposes a visitor is entitled to refuse the marketing and keep the analytics. |

`window.consent.has('embeds')` answers in every configuration, banner or no banner, so
gated code stays on a single path and never has to ask whether consent exists at all.

### Public API

The banner's markup and class names, and `window.consent` (`has`, `grant`, `revoke`,
`open`, plus the `consent:change` event), are public. Projects style the banner from their
own stylesheet — the package CSS is structural and reads the template's design tokens
(`--accent`, `--font-body`, …) for the rest — so **renaming a class breaks their
overrides**. Treat it as breaking and say so in the changelog. To replace the markup
outright: `php artisan vendor:publish --tag=leap-views`.

## Caching

The page tree is memoized per request (`once()`); there is no persistent cache to
invalidate. See [caching.md](caching.md).
