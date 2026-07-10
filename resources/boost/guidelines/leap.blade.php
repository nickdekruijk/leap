# Leap admin panel

This project uses `nickdekruijk/leap`, a Laravel admin panel. Admin modules live in
`app/Leap/*` and CRUD screens are defined in PHP with a fluent API — no Blade or
JS is written per module. Follow these conventions; the full reference is in the
package's `docs/` directory.

## Modules and resources

- Each admin screen is a class in `app/Leap/` extending `NickDeKruijk\Leap\Resource`
  (data CRUD) or `NickDeKruijk\Leap\Module` (custom). They are auto-discovered, plus
  any listed in `config('leap.default_modules')`.
- A `Resource` sets `$model`, an optional `$title`/`$icon`, and an `attributes()`
  method returning an array of `Attribute`s that defines the index columns and the
  editor form.
- Prefer `php artisan leap:module <Model>` over writing a resource by hand — it
  inspects the model's schema/casts and generates the `attributes()` array (types,
  required, unique, sortable, labels, icon) as a starting point. Safe to re-run after
  a migration adds a column: it merges in only the new `Attribute::make()` lines and
  leaves the rest of the file untouched (use `--force` to regenerate from scratch,
  `--dry-run` to preview, `--no-interaction` to skip the confirm/override prompts).

```php
use NickDeKruijk\Leap\Resource;
use NickDeKruijk\Leap\Classes\Attribute;

class Page extends Resource
{
    public $model = \App\Models\Page::class;

    public function attributes(): array
    {
        return [
            Attribute::make('title')->index(1)->searchable()->required(),
            Attribute::make('slug')->unique()->slugFrom('title'),
            Attribute::make('active')->switch()->default(true),
        ];
    }
}
```

## Attribute fluent API

- Inputs: `->switch()`, `->select($values)`, `->radio()`, `->textarea()`,
  `->richtext()` (TinyMCE), `->ace()` (code), `->media()` (file/image), `->pivot()`,
  `->sections(...)`.
- Behaviour: `->index()` (show as column), `->indexOnly()`, `->searchable()`,
  `->sortable()`, `->filterable()`, `->required()`, `->unique()`, `->default()`,
  `->hint('...')` (renders an (i) tooltip).
- Slugs: declare `->slugFrom('title')` on the slug field. (`->slugify('slug')` on the
  source field is the deprecated inverse — prefer `slugFrom`.)
- `->label()`, `->placeholder()` and `->hint()` also accept a per-locale array,
  e.g. `->label(['nl' => 'Titel', 'en' => 'Title'])`.

## Sections (repeatable JSON blocks)

`Attribute::make('sections')->sections(Section $a, Section $b, ...)` stores an array
of blocks in a JSON column. Build each with
`Section::make('name')->view('sections.name')->attributes(Attribute::make(...), ...)`.
Add the `App\Traits\HasSections` trait to the model; it merges uploaded media onto
`sections.{key}.{field}` and adds `_first`/`_last`/`_view` helpers used by the
frontend partials.

## Multilingual editing

Gated on `config('leap.locales')`. When it is an associative array
(`['nl' => 'Nederlands', 'en' => 'English']`) the editor shows a language switcher
and edits translatable fields per locale; when `null` everything is monolingual.
Mark a **section sub-field** translatable with `Attribute::make('body')->translatable()`.
**Top-level** fields derive translatability from the model's Spatie
`$translatable` array (`use Spatie\Translatable\HasTranslations`), so keep the two in
sync.

## Frontend template

`php artisan leap:template` scaffolds a semantic-HTML frontend into the host project:
`PageController` (routing via `getPages()`/`getMenu()`, homepage is the page whose
slug is `/`, optional locale prefixes), the `Page` model with `App\Traits\HasSlug`
(per-locale, sibling-and-locale-unique slugs), self-contained sections, live search,
an admin-editable footer and `sitemap.xml`. Run `leap:template --diff` to preview how
edited copies differ from the current stubs.

## Assets: no npm / Vite

Leap panel CSS is plain CSS, concatenated and cached on request — no compiler. The
template's own styles still compile SCSS on request via `nickdekruijk/minify`
(ScssPhp); both serve JS the same way — **there is no npm/Vite build step** anywhere.
Edit the `.css`/`.scss`/`.js` files and reload. If a change isn't visible, it is a
browser/HTTP cache, not a missing build.

## Caching

The template caches its page tree (`config('leap.cache')`, default on) and invalidates
automatically when a `Page` is saved or deleted. Clear manually with
`php artisan cache:clear`.
