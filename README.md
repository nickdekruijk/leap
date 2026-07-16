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
- **Multilingual editing** — edit and store content per locale, fully opt-in, with
  locale-aware routing, `hreflang`/sitemap and language switching for the frontend.
- **Frontend template** — an accessible, SEO-ready public website scaffolded with one
  command.

## Quick start

```bash
composer require nickdekruijk/leap -W
php artisan migrate
```

> **Why `-W`?** Leap pulls in `laravel/passkeys` → `web-auth/webauthn-lib` → the
> WebAuthn stack, and several packages in that chain still cap `brick/math` at
> `^0.17` — while a fresh Laravel already locks `brick/math` to `0.18` through
> `laravel/framework`. A plain `composer require` only updates the package you
> name, not another package's locked dependency, so it can't downgrade
> `brick/math` and **silently installs an ancient Leap that predates the passkey
> dependency instead** (no error). `-W` (`--with-all-dependencies`) lets Composer
> downgrade `brick/math` to `0.17` and install the current Leap.
>
> To check whether the cap is still there, run `composer why-not brick/math 0.18`
> in your project — it names whatever is still holding the line. Once that comes
> back empty, `-W` is no longer needed. Tracking:
> [pki-framework#86](https://github.com/Spomky-Labs/pki-framework/issues/86).

Add the required traits to your user model (see
[docs/installation.md](docs/installation.md)), then visit `/admin`.

### Your first module

Write it by hand, or generate it from an existing model with
`php artisan leap:module Page` (see [modules-and-resources.md](docs/modules-and-resources.md#generating-a-resource-leapmodule)):

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
- [AI features](docs/ai.md)
- [Frontend template](docs/template.md)
- [Permissions & authentication](docs/permissions-and-auth.md)
- [Configuration](docs/configuration.md)
- [Caching](docs/caching.md)
- [Upgrading to 1.0](docs/upgrading.md)
- [Changelog](CHANGELOG.md)

## Requirements

PHP 8.3–8.4 · Laravel 12/13 · Livewire 3/4.

## License

MIT. See [LICENSE.md](LICENSE.md).
