# AGENTS.md — developing nickdekruijk/leap

This file is the canonical guide for AI agents (and humans) working **on the leap
package itself**. For using leap in a host application, see `README.md` and `docs/`.

## Repository layout

- `src/` — the package. `Resource`/`Module` (admin screens), `Classes/Attribute` and
  `Classes/Section` (the fluent API), `Livewire/` (the `Editor` and other components),
  `Middleware/`, `Controllers/`, `Traits/`, `LeapContext` (request-scoped state).
- `stubs/template/` — the frontend template copied into host projects by
  `leap:template`. **Editing files here does not affect any live site** until a project
  re-runs the command.
- `config/leap.php`, `lang/`, `migrations/`, `resources/` (views, CSS, Boost
  guidelines), `routes/`.
- `tests/` — Testbench + PHPUnit.

## Conventions

- **Code style is Pint (`laravel` preset, see `pint.json`).** Run
  `vendor/bin/pint` before committing; CI enforces `vendor/bin/pint --test`. Don't
  hand-fight the formatter or use a config from another project.
- **Public API is frozen for 1.x.** Do not rename `Attribute`/`Section`/`Module`/
  `Resource` methods — that breaks every host's `app/Leap/*`. Add functionality
  additively and deprecate rather than remove.
- **Multilingual and other new behaviour must stay gated** so `leap.locales === null`
  is byte-for-byte identical to the old behaviour.
- **Request-scoped state** goes through `LeapContext` (`Leap::context()`), not
  Laravel `Context`. The old Context keys are mirrored for 1.x BC only.

## Assets: no npm / Vite

Panel CSS (`resources/css/*.css`) is plain CSS, concatenated and cached on request by
`AssetController` — no SCSS, no compiler. The template's own styles
(`stubs/template/resources/css/*.scss`) still compile via `nickdekruijk/minify`
(ScssPhp), a template-only dependency; JS is served the same way. **There is no build
step anywhere.** Do not add Vite/npm tooling. If a style change isn't visible it is
HTTP/browser caching.

## Testing

```bash
composer install
vendor/bin/phpunit                       # whole suite
vendor/bin/phpunit --filter SchemaTest   # one test
```

Tests boot a minimal Testbench app against an in-memory SQLite database (see
`tests/TestCase.php`). Add a test for every behaviour change. The suite is PHPUnit —
do not introduce Pest without discussing the dependency change first.

> Note: some `TwoFactor*` tests are currently failing independently of recent work;
> confirm a change doesn't *add* failures by comparing against the base commit.

## The stub template

Changes under `stubs/template/` are verified by installing them into a throwaway host
app and exercising the frontend, because they run in the host's context (Herd/nginx
with `public/` as docroot, which the template's ScssPhp import paths rely on —
`php artisan serve` has a different CWD and breaks them). `leap:template --diff` shows
drift between a host's copy and the current stubs.

## Releasing

Update `CHANGELOG.md`, keep `docs/` in sync, and follow semver strictly from 1.0.
CI runs the suite across PHP 8.2–8.4 × Laravel 12/13 × Livewire 3/4
(`.github/workflows/tests.yml`).
