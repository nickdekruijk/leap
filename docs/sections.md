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

## The HasSections trait

Add `App\Traits\HasSections` (shipped by `leap:template`) to the model whose column
holds the blocks. It:

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
