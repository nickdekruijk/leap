# Sections

Sections let an editor build a page from a repeatable, reorderable list of content
blocks stored in a single JSON column. Each block has a type (its own set of fields)
and the frontend renders a Blade partial per block.

## Defining sections

Use `->sections()` on an attribute, passing one `Section` per block type:

```php
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;

Attribute::make('sections')->sections(
    Section::make('slide')
        ->attributes(
            Attribute::make('head')->translatable(),
            Attribute::make('body')->richtext()->translatable(),
            Attribute::make('image')->media(),
        ),
    Section::make('cta')
        ->view('sections.default')   // reuse another partial
        ->attributes(
            Attribute::make('head')->translatable(),
            Attribute::make('body')->translatable(),
        ),
);
```

`Section` methods:

- `Section::make('name')` — the block type; its default frontend view is
  `sections.name`.
- `->attributes(Attribute ...$attributes)` — the fields for this block.
- `->label($label)` — a display label (string or per-locale array).
- `->view('sections.other')` — render a different Blade partial.
- `->withoutView()` — data-only block with no frontend partial.
- `->translatableOnly(...$names)` / `->translatableExcept(...$names)` — mark sub-fields
  translatable in bulk (see below).

## The HasSections trait

Add `NickDeKruijk\Leap\Traits\HasSections` to the model whose column holds the blocks. It:

- casts the JSON column to iterable section objects;
- merges uploaded media onto `sections.{key}.{field}` so a block's `->media()` field
  resolves to real files;
- adds `_first` / `_last` flags per run of same-type blocks (used to open/close a
  wrapping element, e.g. a carousel or a horizontal scroller), plus `_view` and
  `_name`.

## Rendering

The template's `page.blade.php` loops active sections and includes the partial:

```blade
@foreach ($page->sections()->where('active', true) as $section)
    @include($section->_view ?? 'sections.' . $section->_name)
@endforeach
```

Each partial reads the block's fields (`$section->head`, `$section->body`, …) and uses
`_first`/`_last` to wrap grouped blocks.

## Per-locale section fields

Mark a section sub-field with `->translatable()` and it is edited per locale when
`leap.locales` is set, stored as `['nl' => …, 'en' => …]`. `HasSections` resolves it to
the current locale on the frontend. See [multilingual.md](multilingual.md).

### Bulk marking translatable fields

Marking each field by hand is easy to forget. Two `Section` methods do it in bulk,
chained after `->attributes()`:

- **`->translatableOnly('head', 'body')`** — mark exactly the named fields. Explicit and
  safe.
- **`->translatableExcept('button_link')`** — mark every **textual** field
  (plain text, textarea, rich-text) except the named ones. Structural fields — switches,
  media/file pickers, selects, dates, numbers — are skipped automatically, so you rarely
  need to name anything; pass a field only to keep a translatable-looking one shared.

```php
Section::make('default')->attributes(
    Attribute::make('active')->switch(),           // skipped (not textual)
    Attribute::make('image')->media(),             // skipped
    Attribute::make('image_position')->select()->values([...]), // skipped
    Attribute::make('head'),                        // → translatable
    Attribute::make('body')->richtext(),            // → translatable
)->translatableExcept();
```

Both call `Attribute::translatable()` under the hood, so individual `->translatable()`
calls keep working and can be mixed in.
