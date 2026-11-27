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
| `->filterable()` | add a column filter that only offers values in use: a `foreign` or `pivot` by the id of the related record, a `select` or `radio` by its label from `values()` |
| `->required()` | validation `required`; without it an empty `date()` or `datetime()` saves as null |
| `->readonly()` | shown but not editable: not validated and not written on save |
| `->unique()` | validation `unique` on the table |
| `->default($value)` | default value for new records |
| `->validate([...])` | extra validation rules |
| `->hint($text)` | an `(i)` tooltip next to the label. Printed as HTML, so write it yourself and never fill it from a record |
| `->label($label, $labelIndex = null)` | override the label (and column header). Printed as HTML in the editor, so the same goes |
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

**What a slug may contain:** lowercase letters, digits, and single hyphens between them.
An accent is allowed — a Dutch or German word reads better with one, and a sitemap or a
link encodes it anyway. An uppercase letter is not: a URL path is case sensitive, so
`/Praktijk` and `/praktijk` are two addresses, and a site that has both collects
redirects it did not mean to need. `/` is the homepage's, and only a page without a
parent may use it. Empty is fine too: it means "derive one from the title", and in a
locale nobody has translated yet it means the page has no address there.

The field shapes what is typed into it as you go — a space becomes a hyphen, a capital
becomes lowercase — so the correction is something you watch happen rather than
something that turns up later. That is a convenience; the rule is what enforces it, and
it runs per locale.

One asymmetry worth knowing: the suggestion offered for an empty slug comes from
Laravel's `Str::slug()`, which strips accents, so a title "Cliëntroute" suggests
`clientroute`. Typing `cliëntroute` yourself is accepted. The suggestion is deliberately
the conservative one.

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
from the model's Spatie `$translatable` array. To mark several section sub-fields at
once, use `Section::translatableExcept()` / `translatableOnly()` — see
[sections.md](sections.md#bulk-marking-translatable-fields).

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

The preview shows what is stored, and what is stored is not necessarily what TinyMCE would
have let through, so it is cleaned with DOMPurify before it is shown: no scripts, no event
handlers, no iframes. Until that script has loaded, or when it cannot be loaded, the
preview is the text without its markup.

## Filtering rich text

TinyMCE cleans what is typed into it: a pasted `<script>` or an `onclick` does not make
it into the field. That happens in the browser, though, and whoever may save a record can
also send a value straight to the panel, past the editor. The site prints rich text as it
is stored, on the same origin as the panel, so a script saved that way runs for an
administrator who opens the page.

`leap.tinymce.sanitize` filters a rich-text value on the server, at the moment it is
stored, in the editor and in a CSV import. It is off by default, and it needs PHP 8.4 and
one package, version 7.4 or later:

```bash
composer require symfony/html-sanitizer
```

```php
// config/leap.php
'tinymce' => [
    'sanitize' => true,
    // The hosts an iframe may point at
    'sanitize_iframe_hosts' => ['www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com'],
    // What this site uses beyond what TinyMCE writes by default
    'sanitize_allow' => [
        '*' => ['data-aos'],          // an attribute on every element
        'video' => ['autoplay'],      // an attribute on one element
        'button' => ['type'],         // an element that is not allowed otherwise
    ],
],
```

What it does:

- **Scripts, event handlers and `javascript:` links go**, and so do `<style>`, `<form>`,
  `<svg>`, `<object>` and anything else that is not text markup. An element that is not
  allowed goes with what is inside it.
- **An iframe stays only when its host is on the list**, with the attributes an embed
  code carries (`allow`, `allowfullscreen`, `frameborder`, `referrerpolicy`, `loading`).
- **What TinyMCE writes with leap's default options stays**: headings, lists, tables,
  links, images, `class` and `style`, the accordion, video and audio.
- **What is stored is always what the filter wrote**, never what was sent. It writes HTML
  its own way: `é` where TinyMCE writes `&eacute;`, `<br />` for `<br>`. So the first save
  of a record with the filter on rewrites its rich text. What it says stays the same, and
  the next save finds nothing to do. HTML comments do not survive.

  Keeping a value as it was sent when nothing had to go is not safe. A parser sets markup
  aside that a browser reads on the page after all: a stray `<body onload>` ends up on the
  real body, a tag left open at the end is finished by the markup after it, a loose `<td>`
  is a cell once it is printed inside a table. What the filter writes is closed and
  escaped, and has none of that.
- **An address is read the way a browser reads it.** A space around it
  (`href="https://example.com "`) is dropped, and a space in a file name
  (`/storage/files/annual report.pdf`) becomes `%20`: the same address, and the link keeps
  working. A link that is repaired, such as `mailto: info@example.com` with a space after
  the colon, stays a link. Only an address a browser would not follow, or would run, loses
  its `href` or `src`.
- **`sanitize_allow` adds, it never takes away.** An attribute allowed on one element
  stays where it was allowed already, and an element you add carries `class`, `style` and
  whatever is under `'*'`, like every other. Do not add `script`, `style` or an `on…`
  attribute here: that is switching the filter off for it.
- **Rich text only.** A plain input or textarea is escaped where it is shown and is not
  touched. A field that holds an embed code on purpose should be a `textarea()` or
  `ace()`, not `richtext()`.

Look before you turn it on:

```bash
php artisan leap:sanitize              # what would go, how often, in which records
php artisan leap:sanitize --show=Page:12   # one record, stored and filtered
```

The command reads every rich-text field of every module, in sections and per locale, and
writes nothing. It works with the filter off, which is the point. It lists:

- what would go, such as an iframe from a host that is missing from the list or an
  attribute a template relies on. Add those to `sanitize_iframe_hosts` or `sanitize_allow`
  and run it again;
- what would stay but be written differently, such as the `mailto:` link above;
- the records in which a tag or a comment is left open at the end of a value;
- how many values are only written another way. Those are not listed one by one: nothing
  is lost in them.

It also shows whether something is stored now that should not be, as far as a parser can
see it. A loose `<td onmouseover>` outside a table is set aside by the parser and is not
in the list. Saving the record with the filter on removes it all the same.

Existing records are not rewritten when the filter is turned on. Each one is filtered the
next time it is saved.
