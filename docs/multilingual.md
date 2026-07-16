# Multilingual content

Leap can edit and store content per locale. The whole feature is gated on
`config('leap.locales')`:

- **`null`** (default) — monolingual. Everything behaves exactly as if the feature did
  not exist; content is stored as plain strings. Existing projects are unaffected.
- **an associative array** — multilingual. One input is shown per locale and content is
  stored as `['nl' => '…', 'en' => '…']`.

```php
// config/leap.php
'locales' => ['nl' => 'Nederlands', 'en' => 'English'],
```

The first key is the default locale.

## The editor

When `leap.locales` is set and a resource has at least one translatable field, the
editor shows a **language switcher** in the button bar — abbreviated tabs (`NL`, `EN`)
for up to three locales, a dropdown for four or more. One active locale drives all
translatable inputs at once (top-level fields and section fields), and a small locale
badge marks which fields are translatable. Validation makes the default locale
`required` and the other locales optional.

## AI translation

With an AI provider configured, the editor can translate content **into the active
locale** from a chosen source locale — per field (click a field's locale badge) or all
fields at once (the **Translate** button, with an "only empty" or "overwrite all" scope,
covering section sub-fields too). HTML markup is preserved and slug fields stay
slugified. Results fill the form for review; saving is unchanged. Configuration and
details: [ai.md](ai.md).

## Making fields translatable

**Top-level model fields** derive translatability from the model's Spatie
`HasTranslations` configuration — list them in the model's `$translatable` array:

```php
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasTranslations;

    public $translatable = ['title', 'slug', 'description', 'body'];
}
```

Keep the migration columns for these as `json`/`text`.

**Section sub-fields** are marked explicitly, because they have no underlying Eloquent
attribute to introspect:

```php
Section::make('slide')->attributes(
    Attribute::make('head')->translatable(),
    Attribute::make('body')->richtext()->translatable(),
    Attribute::make('image')->media(),   // media stays shared across locales
);
```

Media and pivots are shared across locales in this version. To mark many section
fields at once, use `Section::translatableExcept()` / `translatableOnly()` instead of
per-field `->translatable()` — see [sections.md](sections.md#bulk-marking-translatable-fields).

## Upgrading existing content

Content created before a field became translatable is stored as a **plain string**,
not the `{"nl":"…"}` JSON Spatie expects. Left alone, Spatie reads such a value as
empty, so the field would load blank in the editor and the first save would overwrite
the old text.

Leap guards against this automatically: when a translatable field (top-level or a
`->translatable()` section sub-field) is loaded and its stored value is a legacy plain
string, the editor wraps it as `[defaultLocale => value]`. The old content shows up in
the default-locale tab, and saving writes it back as proper JSON. **Opening a legacy
record and saving it — even without editing — persists this migration** (the raw column
changes from a string to JSON, so the record is dirty and gets saved).

This is a **per-record, lazy** migration: it only runs for records you open. A record
you never open keeps its legacy string in the database, and because Spatie reads that as
empty, its **frontend** output stays blank until the record is opened and saved once. If
you need every existing row converted up front, run a one-off migration that wraps each
legacy value into `{"<defaultLocale>": "<value>"}` before enabling the translatable
field.

## Frontend

Spatie resolves translated attributes to the current app locale automatically, so
`$page->title` returns the active-locale value. `HasSections` does the same for
translatable section fields.

The template's live search (`<livewire:search />`) is locale-aware too: title,
description and section content are matched against the **active locale only**, so a
term that exists only in another language's translation does not surface the page. It
degrades safely on non-translatable or not-yet-migrated (plain-string) columns.

The `leap:template` frontend adds locale-aware routing: the default locale is
unprefixed (`/over-ons`) and secondary locales are prefixed (`/en/about`), with a
language switcher and `hreflang` alternates. Per-locale slugs come from
[`HasSlug`](template.md); a missing translated slug falls back to the default locale so
a page is never unreachable.

## Routing & URLs

The routing primitives live in the package, so projects can build locale-aware URLs
without copying template code — for the page tree and for content types on their own
routes.

### The prefix rule

The default (first configured) locale is served **unprefixed**; every other locale is
prefixed with `/<locale>`. One helper is the single source of truth, used by the
template's routing, the language switcher and the sitemap:

```php
use NickDeKruijk\Leap\Leap;

Leap::localeDefault();      // 'nl'  (null when monolingual)
Leap::localePrefix('en');   // '/en'
Leap::localePrefix('nl');   // ''    (default locale)
Leap::localePrefix();       // prefix for the active locale
```

`Leap::detectLocale($segments)` strips a leading locale segment from a URL's path
segments (by reference) and applies it with `app()->setLocale()`. With no locale segment
it applies the default locale explicitly; it is a no-op only when monolingual. The
template's `PageController::route()` calls it before matching the page tree.

### `leap.locales` vs `APP_LOCALE`

They answer different questions, and on a multilingual site they are allowed to disagree.

**`leap.locales` decides the site.** Its first key is the locale served unprefixed, so it
decides every frontend URL. It lives in `config/leap.php`, which is deployed with the code
— a routing decision has to be, or the same commit serves different URLs per environment.

**`APP_LOCALE` decides everything else**: the admin panel, the console, queues and mail. It
lives in `.env`, which is not in version control.

So an English admin on a Dutch site is a normal thing to want:

```
leap.locales = ['nl' => 'Nederlands', 'en' => 'English']   # / is Dutch, /en is English
APP_LOCALE=en                                              # /admin and the console in English
```

`leap:template` writes both to match when it configures the languages, but leaves both
alone once `leap.locales` has been set by hand — so a re-run cannot undo the setup above.

On a **monolingual** site (`leap.locales` is `null`) there is nothing to prefix and
`detectLocale()` does nothing, so `APP_LOCALE` alone decides the site's language.

### Content types outside the page tree

For a translatable model with its own routes — services, stories, blog posts — register
one route per locale with the `Route::leapLocalized()` macro. The URL **segment can
differ per locale** and each group gets the right prefix plus the `SetLeapLocale`
middleware (which sets the request locale) automatically:

```php
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

// nl: /diensten/{slug}   en: /en/services/{slug}
Route::leapLocalized(['nl' => 'diensten', 'en' => 'services'], function (string $locale, string $segment) {
    Route::get($segment.'/{slug}', [ServiceController::class, 'show'])->name('service.'.$locale);
});
```

Name the routes `<name>.<locale>` — that is the convention `HasLocaleRouting` expects.
The controller resolves the record by the active-locale slug:

```php
Service::whereJsonContains('slug->'.app()->getLocale(), $slug)->firstOrFail();
```

### `HasLocaleRouting`

Add the trait to such a model (it needs Spatie `HasTranslations` with a translatable
`slug`) to get per-locale URLs for the language switcher and `hreflang`, without writing
them by hand:

```php
use NickDeKruijk\Leap\Traits\HasLocaleRouting;

class Service extends Model
{
    use HasTranslations, HasLocaleRouting;
    public $translatable = ['title', 'slug', 'description'];
}
```

```php
$service->localeUrls();
// ['nl' => ['name' => 'Nederlands', 'url' => '/diensten/onderhoud', 'active' => true],
//  'en' => ['name' => 'English',    'url' => '/en/services/maintenance', 'active' => false]]

$service->localeUrl();        // active-locale URL
$service->localeUrl('en');    // a specific locale, or null when not routable there
```

A locale with no slug translation is omitted (not routable there). The URLs are already
prefixed — the named route carries the prefix, so nothing is added twice.
`localeRouteName()` defaults to the singular snake_case class basename (`service`);
override it if your route names differ.

### hreflang and the language switcher

Both come from the same per-locale URL map. For the page tree use
`PageController::localeUrls($page)`; for a `HasLocaleRouting` model use
`$model->localeUrls()`. In the layout `<head>`:

```blade
@foreach ($localeUrls as $code => $alt)
    <link rel="alternate" hreflang="{{ $code }}" href="{{ url($alt['url']) }}">
@endforeach
```

Document `<title>` and Open Graph image come from
[`HasDocumentMeta`](template.md#hasdocumentmeta) (`documentTitle()` / `metaDescription()` / `ogImageUrl()`),
and the sitemap lists every routable locale per record — see
[template.md](template.md#seo).
