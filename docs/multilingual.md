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
editor shows a **language switcher** in the button bar — tabs for two locales, a
dropdown for more. One active locale drives all translatable inputs at once (top-level
fields and section fields), and a small locale badge marks which fields are
translatable. Validation makes the default locale `required` and the other locales
optional.

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

Media and pivots are shared across locales in this version.

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
