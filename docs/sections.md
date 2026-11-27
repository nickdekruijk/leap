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
- `->divider()`: this block structures the ones below it, like a tab or a chapter. See
  [the list in the editor](#the-list-in-the-editor).
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

[`HasReadingTime`](template.md#hasreadingtime) reads the same blocks to count how long an
article takes to read, per locale and skipping the ones switched off.

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

## The list in the editor

A page can hold dozens of blocks, so the editor shows them as a list and opens the one
you are working on.

- **A closed block says what it holds.** The fields marked `->sectionTitle()` when there
  are any, and otherwise the first text in the block, without its markup and cut to one
  line. Six blocks of the same kind can be told apart without opening them. A block with
  an image shows the first one beside it.
- **A block switched off with an `active` field is struck through**, the way a row in the
  index is. It is the same test `HasSections` uses to leave it off the page.
- **A record with more than two blocks opens with them closed.** A block somebody opened
  or closed keeps that state. Set `leap.sections.collapsed` to `false` to open every
  block nobody closed, as before 1.24.
- **Blocks are added where they are needed.** On hover a row shows Insert, Duplicate and
  Delete. Insert opens the list of blocks and adds one right above the row, and a line
  shows where; on the first row that is the top of the list. Duplicate puts a copy right
  below, with its media. "Add section" under the list adds a block at the end.

### Dividers

Some blocks are structure rather than content: a tab, a chapter, the start of a column.
Mark such a block with `->divider()`:

```php
Section::make('tab')->divider()->attributes(
    Attribute::make('title')->sectionTitle(),
),
```

Nothing changes in what is stored or rendered on the site. In the editor the block
becomes a heading in the list and the blocks up to the next divider are its group. A
group can be folded away, and a folded divider takes its group along when it is dragged.
An open divider moves alone, which is how a block is moved from one group to the next.
Blocks in front of the first divider belong to no group. Insert on a divider adds a block
at the end of the group above it, and the line shows that by stepping in.

### What is stored about the list

Whether a block is open (`_closed`), whether a group is folded (`_folded`) and the label
of a closed block (`_title`) are stored in the JSON with the record, so the list looks
tomorrow the way it was left. They are not content. Opening or closing a block does not
count as an unsaved change, and saving a record in which nothing else changed stores the
state without touching `updated_at`, without model events and without a log entry. A
template can ignore all three keys.
