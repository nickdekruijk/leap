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

## Permissions

Each module is subject to the current user's role permissions (`read`, `create`,
`update`, `delete`). A user without `read` permission does not see the module and
gets a 404 — see [permissions-and-auth.md](permissions-and-auth.md).

## Request-scoped context

The active module, the resolved permission map and the current role name are kept in
a request-scoped `LeapContext` service, reachable via `Leap::context()`
(`->module()`, `->permissions()`, `->permissionsFor($module)`, `->roleName()`). Prefer
it over reading Laravel `Context` keys directly.
