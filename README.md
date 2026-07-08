# Leap — Laravel Easy Admin Panel

Leap is a Laravel package that gives you a full admin panel with almost no boilerplate.
Admin screens are defined in PHP with a fluent API — no per-screen Blade or JavaScript —
and it ships an optional, semantic-HTML frontend template (pages, navigation, sections,
search, SEO) for the public site.

Built with Livewire; styling is compiled on request (no npm/Vite build step).

## Features

- **Resource modules** — declare a model's CRUD screen with a fluent `Attribute` API:
  list columns, editor form, validation, search, sort, filter, CSV import/export.
- **Media, sections and rich content** — file/image uploads, repeatable JSON
  section blocks, TinyMCE and Ace editors.
- **Roles & permissions**, **two factor authentication**, **passkeys** and
  **password reset** out of the box.
- **Multilingual editing** — edit and store content per locale, fully opt-in.
- **Frontend template** — an accessible, SEO-ready public website scaffolded with one
  command.

## Quick start

```bash
composer require nickdekruijk/leap
php artisan migrate
```

Add the required traits to your user model (see
[docs/installation.md](docs/installation.md)), then visit `/admin`.

### Your first module

```php
namespace App\Leap;

use App\Models\Page;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

class PageResource extends Resource
{
    public $model = Page::class;

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

Drop that in `app/Leap/` and it appears in the panel — list, editor, validation and
permissions included.

### The frontend template (optional)

```bash
php artisan leap:template
```

Scaffolds a public website: pages, navigation, content sections, live search, an
admin-editable footer, per-page SEO and a sitemap. See
[docs/template.md](docs/template.md).

## Documentation

- [Installation](docs/installation.md)
- [Modules and resources](docs/modules-and-resources.md)
- [Attributes reference](docs/attributes.md)
- [Sections](docs/sections.md)
- [Multilingual content](docs/multilingual.md)
- [Frontend template](docs/template.md)
- [Permissions & authentication](docs/permissions-and-auth.md)
- [Configuration](docs/configuration.md)
- [Caching](docs/caching.md)
- [Upgrading to 1.0](docs/upgrading.md)
- [Changelog](CHANGELOG.md)

## Requirements

PHP 8.2–8.4 · Laravel 12/13 · Livewire 3/4.

## License

MIT. See [LICENSE.md](LICENSE.md).
