# Content types

> **`leap:content` ships in the dev-only [`nickdekruijk/leap-template`](https://github.com/nickdekruijk/leap-template)
> package** (`composer require --dev nickdekruijk/leap-template`). The runtime it
> generates (models, the registry, the card engine) lives in your app + leap.

The frontend template can list **content types** — models rendered as a row of cards: a
teaser on a page, a filterable overview of their own, and a detail page each. News,
events and any hand-ordered collection (projects, products, artists, albums …) are all
the same machinery, driven by one registry.

## The registry

Every content type is registered in `config/leap.php`:

```php
'content' => [
    'news' => App\Models\News::class,
    'events' => App\Models\Event::class,
    'products' => App\Models\Product::class,
],
```

The key is the section/route slug and the array order is the order the teasers appear in.
This is the single source of truth: `PageController`, the `Page` admin resource, the live
search and `sitemap.xml` all read `PageController::indexModels()` (the registry filtered
by `class_exists`). Add a type with `php artisan leap:content`, or by hand.

## Generating a type — `leap:content`

```bash
php artisan leap:content News                 # archetype guessed from the name
php artisan leap:content Product              # generic (sortable) type
php artisan leap:content Fair --archetype=event
php artisan leap:content Story --no-tags
```

It writes a model, a Leap resource, a migration, a factory and a seeder, and appends the
registry line. It is idempotent: an existing type is skipped, so re-running the installer
is safe. `leap:content` needs the template (it builds on `App\Models\Page`); for an
admin-only resource on an existing model use [`leap:module`](modules-and-resources.md).

### Archetypes

The **archetype** decides the shape. It is guessed from the name prefix (`News*` → news,
`Event*` → event, otherwise generic) or set with `--archetype`:

| Archetype | Fields & behaviour |
| --- | --- |
| `news` | `published_at` **required** — future-dated items stay hidden until then. Newest first. |
| `event` | `date` + optional `start_time`/`end_time`, plus a derived `ends_at`. `published_at` is **optional** and acts as a scheduled-visibility gate (empty = now, future = staged). Scopes `future()`/`past()`. |
| `generic` | No dates. Hand-ordered with a `sort` column (drag to reorder in the admin). |

All three are taggable through the shared `Tag` (unless installed with `--no-tags`), share
the page section blocks, and get a per-locale slug via `HasSlug`.

The name is free, so `leap:content Newsitem` gives a news-shaped model called `Newsitem`,
and `leap:content Fair:event` an event-shaped `Fair`.

**Name content types in English, whatever language the site speaks.** The name is code: it
becomes the class, the table (`Str::plural()`, which is English), the `leap.content` key and
the section name. It is never a URL — an overview lives at the slug of the page whose section
lists that type, and detail pages at `{that slug}/{item slug}`. Both are per locale and both
are the editor's to change, so a Dutch site is `/berichten` and its English twin `/news`,
from one `News` model. A Dutch class name buys nothing a visitor can see and costs
`Str::plural()` its accuracy — `Bericht` becomes the table `berichts`.

`--plural=` is there for the exception (`--plural=people`), not for translating.

## Events: `ends_at`, `future()` and `past()`

`ends_at` is computed on save from `date` + `start_time`/`end_time`, so `future()`/`past()`
stay simple indexed queries:

- **no end time** → the rest of the day (a running or all-day event stays *upcoming* until
  midnight);
- **no start time** → all day;
- **end at or before the start** → it runs past midnight and ends the next day
  (22:00–02:00 ends at 02:00 tomorrow);
- otherwise → that day at the end time.

`future()` is `ends_at >= now()` (so a currently-running event still counts as upcoming),
`past()` the opposite. An event index section has a **period** select — *upcoming*
(default), *past*, or *both*.

## Overview, teaser and detail

- The **overview page** is an ordinary `Page` carrying an unlimited, untagged section of
  the type. Its slug is the URL base (`/news`, `/events`); rename it per locale in the
  admin. Detail pages hang under it (`/news/{slug}`), so each item has exactly one URL.
- A **teaser** is the same section with a `limit` — a horizontal card row that links to the
  overview. Drop one on any page.
- A **tag-fixed** section names a tag and lists only those items.

A type **without** an overview page degrades gracefully: no detail routes, its cards render
without a link, and it is skipped in search and the sitemap. Delete or deactivate the
overview page in the admin to get that.

## Tags

`Tag` is one shared, polymorphic, translatable vocabulary (no slug column — the filter
slugifies the active-locale name on the fly). The overview shows a chip per tag its items
carry; the chosen tag lives in the URL (`?tag=glas`) and filters client-side. Leave tags
out entirely with `--no-tags`.

## The card view

Every type renders through `resources/views/sections/items.blade.php` and the shared
`<x-items-section>` component — one home for the card markup (whole-card link, equal
height, hover/focus states, the chip row). A type needs no blade of its own. Card styling
is generic in `template.scss`; the brand colours are tokens in `project.scss`
(`--accent-hover-bg`, `--items-columns`, …).

## Adding a hand-written model

Not everything has to be generated. Add `use App\Traits\HasTags;` (optional), a per-locale
`title`/`slug`/`intro`/`description`/`sections` shape, a `scopeActive`, and register the
class in `config('leap.content')`. Discovery does the rest.
