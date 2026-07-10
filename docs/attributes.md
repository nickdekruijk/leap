# Attributes

`NickDeKruijk\Leap\Classes\Attribute` is the fluent builder that defines a field in a
resource — both its list column and its editor input. Start with
`Attribute::make('column_name')` and chain modifiers.

```php
Attribute::make('title')->index(1)->searchable()->required();
```

## Input types

| Method | Input |
| --- | --- |
| *(default)* | text input |
| `->textarea($rows = 3)` | multi-line text |
| `->richtext()` | TinyMCE rich editor (see "Lazy rich-text" below) |
| `->ace()` | Ace code editor |
| `->switch()` | boolean toggle |
| `->select($values)` | dropdown |
| `->radio()` | radio group |
| `->media()` | file/image upload (see [sections.md](sections.md) and media) |
| `->pivot()` | many-to-many relation picker |
| `->sections(...)` | repeatable JSON blocks (see [sections.md](sections.md)) |
| `->password()` | password input (hashed on save) |

## Behaviour modifiers

| Method | Effect |
| --- | --- |
| `->index($order = true)` | show as a list column (optionally ordered) |
| `->indexOnly()` | list column only, not in the editor |
| `->searchable()` | include in the index search |
| `->sortable()` | user-sortable column |
| `->filterable()` | add a column filter |
| `->required()` | validation `required` |
| `->unique()` | validation `unique` on the table |
| `->default($value)` | default value for new records |
| `->validate([...])` | extra validation rules |
| `->hint($text)` | an `(i)` tooltip next to the label |
| `->label($label, $labelIndex = null)` | override the label (and column header) |
| `->placeholder($text)` | input placeholder |

## Slugs

Declare the relationship on the **slug** field:

```php
Attribute::make('slug')->unique()->slugFrom('title');
```

`slugFrom('title')` makes the `title` field live so the slug placeholder updates as
you type, and per locale when multilingual. `->slugify('slug')` (declared on the
*source* field, pointing forward) is the equivalent form from the other end — use
whichever reads better for your module.

The template's `HasSlug` trait does the actual persistence and uniqueness on save; see
[template.md](template.md).

## Per-locale labels, placeholders and hints

`->label()`, `->placeholder()` and `->hint()` accept a per-locale array, resolved to
the current locale:

```php
Attribute::make('title')
    ->label(['nl' => 'Titel', 'en' => 'Title'])
    ->placeholder(['nl' => 'Voer een titel in', 'en' => 'Enter a title']);
```

## Translatable values

To edit a field's **value** per locale (not just its label), see
[multilingual.md](multilingual.md). Section sub-fields use
`Attribute::make('body')->translatable()`; top-level fields derive translatability
from the model's Spatie `$translatable` array.

## Lazy rich-text

Initializing TinyMCE for every `->richtext()` field when the editor opens is slow when
a record has many of them (e.g. lots of sections). Leap can instead show each field's
**rendered HTML** as a click-to-edit preview and only boot TinyMCE for the field you
click, tearing it down again on save. This is controlled by two config toggles
([configuration.md](configuration.md)):

- `leap.tinymce.lazy` — standalone (top-level) rich-text fields. Default `false`
  (TinyMCE loads immediately, as before).
- `leap.tinymce.lazy_sections` — rich-text fields inside repeatable sections. Default
  `true` (click to edit).

The preview looks like a field with a pencil affordance on hover, and shows a "click to
edit" hint when empty. Set `lazy_sections` to `false` to restore immediate editors
everywhere.
