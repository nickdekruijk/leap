# Modules and resources

Every screen in the Leap panel is a class in `app/Leap/`. There are two base types:

- **`Resource`** — a data CRUD screen bound to an Eloquent model. You describe the
  index columns and the editor form once, in PHP, and Leap renders the list, the
  editor, validation, media handling and permissions for you.
- **`Module`** — a custom Livewire-backed screen when you need something that is not
  straightforward model CRUD.

## Discovery

Modules are found automatically by scanning `app/Leap/`. Packages (or you) can also
register modules explicitly by adding their class names to
`config('leap.default_modules')`. Both sources are merged at request time, so a
package that self-registers a module needs no config entry in the host app.

A module's slug identifies it — it is the navigation entry and the route name
`leap.module.{slug}` — so two modules cannot share one. When they do, the last
registration wins, and the `app/Leap/` scan runs after `default_modules`: **your own
module replaces one a package registered under the same slug.** That is how you override
a package's screen — copy it into `app/Leap/`, change what you need, and the package's
version steps aside. Conversely, if a package starts shipping a module you already had
your own copy of, delete yours to move to the package's version. Navigation items with
`$slug = false` (like Logout) register no route and are never deduplicated.

## A resource

```php
namespace App\Leap;

use App\Models\Page;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

class PageResource extends Resource
{
    public $model = Page::class;

    // Optional: a title (string or per-locale array) and an icon
    public $title = ['nl' => 'Pagina\'s', 'en' => 'Pages'];

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

- `attributes()` returns an ordered array of [`Attribute`](attributes.md)s. Those with
  `->index()` become sortable list columns; all non-`indexOnly` attributes appear in
  the editor form.
- The index shows search, sort, filter, pagination and CSV import/export based on the
  attribute flags.

## Generating a resource: `leap:module`

`php artisan leap:module <Model>` generates the `App\Leap\<Model>` class above for you
by inspecting the model's table and casts, so you start from a working resource
instead of a blank file.

`leap:module` is part of the core leap package — it is how you add an admin panel to an
existing app, so it stays available with a plain `composer require nickdekruijk/leap`.
(The frontend-website commands `leap:template`/`leap:content` are the ones that live in
the separate dev package [`nickdekruijk/leap-template`](https://github.com/nickdekruijk/leap-template).)
It refuses to run on `APP_ENV=production` without `--force`.

```bash
php artisan leap:module Event              # bare name, resolves to App\Models\Event
php artisan leap:module App\Models\Event   # or a full FQCN
```

Options:

- `--name=` — the generated class name (defaults to the model's basename)
- `--icon=` — override the guessed blade-icon
- `--force` — fully regenerate the file instead of merging (see below)
- `--dry-run` — print the generated/merged code, write nothing

### What it detects

Per column, from the schema and the model's `$casts`:

- **type** — boolean/`tinyint(1)` → `->switch()`; a foreign key or a `*_id` column with
  a matching model → `->foreign()`; a backed enum cast → `->select()->values()`; date/
  datetime/time columns; `text`/`longtext` → `->richtext()` for `body`/`content`/`intro`,
  `->textarea()` otherwise; `email`/`password`/`slug` get their dedicated methods.
- **required** — `NOT NULL` with no default.
- **unique** — a single-column unique index → `->unique()`.
- **sortable** — an int column named `sort`/`position`/`order` → `->sortable()` plus the
  module's `$orderBy`.
- **default sort order** — when there's no sort column, a single, confidently-named date
  column becomes `$orderBy`/`$orderDesc`: `created_at`/`published_at`/`posted_at` sorts
  newest-first, `event_date`/`start_date`/`starts_at`/`date` sorts soonest-first.
  Ambiguous (multiple candidates) or no match at all → left alone, no guess made.
- **`$active`** — a boolean column named (or containing) `active`, `published`,
  `enabled` or `visible` — e.g. `is_active` matches too.
- **icon** — guessed from the model name (`Event` → `fas-calendar-days`, `User` →
  `fas-users`, `News`/`NewsItem` → `fas-newspaper`, …), falling back to `fas-table`.
- **labels** — humanized from the column name, duplicated across every locale in
  `config('leap.locales')`.

Nothing here is final — every detected value is a starting point you can edit
afterward, in the file or via the interactive prompts below.

### Interactive mode

By default the command asks you to confirm or override each detected value (field
type, required, label) — always pre-filled with the detected value, never asked
blind. Pass `--no-interaction` to skip every prompt and accept all detected defaults.

If the app is running in a non-English locale and `leap.locales` is configured, the
label prompt asks for the label **in that locale** specifically, and keeps the
humanized text as the `en` entry — instead of storing an English guess under your own
locale's key.

### Updating an existing module

If `app/Leap/<Name>.php` already exists, `leap:module` does **not** overwrite it. It
scans the file for `Attribute::make('column')` calls already present and appends only
the columns that are missing — typically after a column was added to the model's
migration. Your hand-written lines (custom labels, hints, ordering) are left
untouched. Pass `--force` to discard the file and regenerate it from scratch instead.

## Permissions

Each module is subject to the current user's role permissions (`read`, `create`,
`update`, `delete`). A user without `read` permission does not see the module and
gets a 404 — see [permissions-and-auth.md](permissions-and-auth.md).

## Request-scoped context

The active module, the resolved permission map and the current role name are kept in
a request-scoped `LeapContext` service, reachable via `Leap::context()`
(`->module()`, `->permissions()`, `->permissionsFor($module)`, `->roleName()`). Prefer
it over reading Laravel `Context` keys directly.
