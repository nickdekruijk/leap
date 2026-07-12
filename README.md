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
composer require nickdekruijk/leap
php artisan migrate
```

> **Adding Leap to an existing project?** If `composer require` fails to
> resolve with an error about `brick/math` being "fixed" to a locked
> version, run instead:
> ```bash
> composer require nickdekruijk/leap "brick/math:^0.17"
> ```
> or add `-W`/`--with-all-dependencies`. This happens because Leap pulls in
> `laravel/passkeys` → `web-auth/webauthn-lib` → `spomky-labs/cbor-php`,
> and `cbor-php` doesn't support `brick/math` beyond `^0.17` yet — while
> `laravel/framework` alone is happy to lock it to a newer version.
> Composer's default (non-`-W`) `require` only lets you update packages you
> name explicitly on the command line, not the dependencies of a package
> you're adding, so an already-locked `brick/math` needs to be named
> directly to be resolved down. This isn't needed on a brand-new project
> where Leap is added to `composer.json` before the first `composer
> install` — remove this workaround once `cbor-php` supports newer
> `brick/math` releases.

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
