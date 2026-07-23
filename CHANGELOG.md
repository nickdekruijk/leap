# Changelog

All notable changes to `nickdekruijk/leap` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.10.17] — 2026-07-23

### Added

- **A slug can follow its title, on your terms.** When you change a title in the editor, an
  unedited slug on a freshly created record follows it automatically (you are still setting the
  page up). On an older record — or once you have edited the slug by hand — the change instead
  offers an inline "update the slug to …?" suggestion right under the slug field, so a live,
  indexed URL is never changed without a click. The window is configurable via the new
  `leap.slug_follow_minutes` config (default 60, `0` = always ask). The suggestion renders on the
  slug field's own label row, right after its hint. `leap:module` now also emits the slug field
  directly after its title (whatever the column order), so a generated module shows the prompt
  where you are looking.

### Fixed

- **Editing a title no longer crashes the editor on a multilingual page.** The editor refreshes a
  slug field's placeholder from its source field as you type, but on a translatable source Livewire
  can hand the hook the whole per-locale array rather than the active locale's string. `Str::slug()`
  was then given an array and threw "Array to string conversion" — hit, for example, when changing
  the Dutch title of a page that has no English content. The value is now narrowed to the active
  locale first, exactly as `refreshSlugPlaceholders()` already does.

- **A page written in only one language no longer forces the default locale.** A required
  translatable field was required specifically in the default (first configured) locale, so on a
  site whose first locale is English a Dutch-only page failed with "the title field is required" for
  the empty English tab. A required translatable field is now required in at least one locale, with
  a single clear message when no locale is filled.

- **An untranslated locale no longer borrows another locale's slug.** `HasSlug` derived a locale's
  slug from the default-locale title when its own title was empty, so saving a page written in only
  English gave its Dutch slug the English title's slug. Each locale now derives its slug from its own
  title only; a locale without a title gets no slug (empty = not routable there). Already-borrowed
  slugs are left untouched — clear the field and save to drop one.

- **Editor validation messages name the field and its language.** A per-locale validation error
  showed the raw `data.title.en` path, and live (as-you-type) validation used Laravel's default
  wording — so emptying a title showed both "…required when none of data.title.nl are present" while
  typing and a second message on save. The editor now supplies its own messages and per-locale field
  labels (e.g. "Title (English)"), so live and save-time validation read the same and every
  per-locale message names the field and language instead of a dotted path.

## [0.10.16] — 2026-07-22

### Fixed

- **A translated field shows its translation straight away, and without escaped slashes.** Two
  faults in the same click. The prompt was built with `json_encode()` but without
  `JSON_UNESCAPED_SLASHES`, so the model was handed `<\/p>` and — told to preserve the markup
  exactly — handed it back verbatim, into the editor and then into the database. And a rich-text
  field sits in `wire:ignore` and is only read into TinyMCE when the editor opens, so a value
  written on the server reached neither the editor nor the click-to-edit preview: the translation
  arrived, invisibly, and only showed itself after switching language tabs, which rebuilds the
  field. Translating now announces itself and the field pulls the new value back in.

- **The spinners spin, and the upload row fades out.** Both `@keyframes` blocks sat nested inside
  a selector, and CSS nesting only permits conditional at-rules there — so browsers dropped them,
  the animation names were never defined, and every `animation` referring to them did nothing at
  all. The AI alt-text button had therefore never turned while it worked, and a finished upload
  never faded. Both are at the top level now, and a test walks the served stylesheet to keep them
  there. The translate button had a second fault of the same kind: it sets `leap-alt-generating`
  alone while the rule demanded `.leap-alt-generate-btn` as well, so it matched nothing.

- **A dialog opened from the editor covers the window instead of the panel.** The editor slides in
  with a `transform`, and a transformed element becomes the containing block for everything
  `position: fixed` inside it. Every modal opened there was therefore bounded by the panel and
  scrolled along with its content rather than staying put over the page. They are rendered at the
  admin root now, through a `teleport` prop on the modal component — at `.leap` rather than the
  body, because the font lives there and outside it a dialog falls back to the browser default.

- **A module that allows CSV import no longer dies on its own index page.** The index template
  read `$this->allowImport['type']` — a key nothing sets, generates or documents, and that no
  other code reads. A resource declaring `$allowImport` the way it is meant to, with the columns
  a file may hold and the attributes they fill, therefore threw `Undefined array key "type"`
  before drawing a single row. CSV is the only import there is, so the check now defaults to it;
  set the key explicitly to hide the button.

### Added

- **Generate an image with AI, next to the button that browses for one.** A media field had one way
  in: pick a file that already exists. Filling a fresh page section therefore stalled on finding
  stock photography before the section could be finished. A wand button beside the browse button
  now opens a dialog whose prompt is **prefilled from the section's own content** — the record
  title plus that section's text, at the language tab being edited, markup stripped — so the image
  is about the copy it sits next to. The file manager's header has the same button for a free-form
  prompt.

  Off by default, like the other AI features: set `leap.ai.image.provider` to `gemini` or `openai`
  (Anthropic has no image API). Generating only produces a preview — the bytes wait in the cache
  and *Use image* is what stores the file, so a result you reject leaves nothing behind. The result
  is always a JPEG at the aspect ratio you picked, cropped by Leap rather than left to whichever
  canvas sizes the provider happens to offer, and clicking it opens it full screen before you
  commit. Alt text is generated for the new image in the same pass, when the `alt_text` task is
  configured and `leap.ai.image.alt_text` is left on.

  Images are stored **per module**: `leap.ai.image.folder` defaults to `{module}`, so a Page's
  images land in `pages/` and a News item's in `news/`. The folder name comes from the module
  class, not its translated title — it does not move when the admin language changes.

  The dialog shows what a generation costs: an estimate before, the actual amount after. Both are
  computed from a rate per model rather than reported by the provider, which returns token counts
  only — so the amounts exclude VAT and ignore free tiers. The rates ship with the package rather
  than in the published config, where they would freeze on the day they were published; they are
  refreshed with an update, and `leap.ai.pricing` overrides one. Where a provider charges by
  quality — OpenAI does, up to 35x between low and high — the estimate follows
  `leap.ai.image.quality`, quoting the ceiling while that is left at the provider's `auto`. A model
  with no known rate shows no price rather than a wrong zero. Every generated image records its
  model, prompt and cost in the media row's `meta['ai']`.

  See [docs/ai.md](docs/ai.md).

- **Live demo site.** [leap.nickdekruijk.nl](https://leap.nickdekruijk.nl) runs a stock
  `leap:template` install; log in on [/admin](https://leap.nickdekruijk.nl/admin) with
  `info@example.com` / `leapdemo`. The site resets itself to its seeded state 15 minutes
  after the last change, so visitors can safely try everything.

## [0.10.15] — 2026-07-21

### Fixed

- **A section switched off no longer takes a wrapper's opening or closing tag with it.**
  `sections()` marked `_first` and `_last` across every section it read, active or not, and left
  the filtering to the template — which then dropped whichever section carried the mark. A
  carousel is a run of slide sections: the first opens `<section class="slider">` and the last
  closes it. Deactivate the last slide and the closing tag went with it, so the sections below
  rendered *inside* the carousel — fixed height, `overflow: hidden` — and drew over it. With two
  slides, switching off either one broke it.

  `sections()` drops inactive sections itself now, before the run is marked, so a template can no
  longer get the order wrong. A section with no `active` key at all is kept, as before.

  Templates filtering with `->where('active', true)` keep working; the filter is simply redundant
  now. The stub views in leap-template have had theirs removed.

## [0.10.14] — 2026-07-20

### Fixed

- **`showIf()` reads a translatable trigger at the locale being edited.** The `x-show` it
  produced pointed at the trigger field itself, which is right for a plain one — but a translatable
  section field is stored per locale, `{"nl": "", "en": ""}`, and in JavaScript an object is always
  truthy. So the dependent field appeared the moment the trigger was touched in any language, and
  stayed once it was cleared again: the one thing the option exists to prevent.

  The path now reaches into the active locale when the trigger is translatable, with optional
  chaining because the key does not exist until the field is first written to. A plain trigger is
  read exactly as before, and a trigger naming a field that is not in the section falls back to the
  old path rather than raising.

- **A hidden field no longer leaves a gap where its row was.** The `x-show` went on a `<div>`
  wrapped around the field, which put an element between the fieldset and its children — and the
  fieldset lays those out itself, so a hidden field still took up its row. It sits on the field's
  own `<label>` now, which is the root every input component renders, so one place covers all
  eleven of them and the wrapper is gone.

### Changed

- **`showWhenTrue()` is now `showIf()`.** The old name promised a boolean, while any truthy value
  has always shown the field — a text field with something in it counts, which is the whole point
  of the fix above. `showWhenTrue()` stays as a deprecated alias and sets the same thing, so
  projects using it keep working untouched.

## [0.10.13] — 2026-07-20

A user without a role sees nothing in the panel — `RequireRole` 403s them — and `leap:user`
could only fix that by asking a question, which a scripted install has nobody to answer.

### Added

- **`leap:user --role` attaches a role without a prompt.** Bare (`--role`) takes the first
  role, `--role=superuser` or `--role=1` names one. An unknown name fails with a message
  instead of leaving an account that cannot log into anything.

### Fixed

- **A pending invitation no longer counts as a role.** The "does this user already have a
  role" check ignored the pivot's `accepted` column, so a user whose only role was still
  unaccepted was left alone — and then 403'd by `RequireRole`, which only looks at accepted
  ones. Such a row is now accepted rather than duplicated.

## [0.10.12] — 2026-07-17

A translatable attribute is stored as json, and three things asked the database for it by
column name — getting `{"nl": "Aap", "en": "Ape"}` where they meant the text in it. Ordering
was reported; the other two turned up looking for more of the same. An index filter had a
fault of the same shape: it read what the index had rendered instead of asking the database.

### Fixed

- **An index filters a foreign or pivot column by id, not by the text it renders.** A pivot
  column renders as the values of the row joined into one string, and the filter was built
  from that string: an article tagged both Update and Announcement offered "Update,
  Announcement" as a filter option of its own, and picking Update alone returned nothing — the
  filter was an exact match in PHP on the rendered value, so a single tag could never equal a
  joined pair.

  Both ends now speak ids, and the options come from `Attribute::getValues()`, so the `scope`,
  `orderBy` and `index` columns of the attribute keep deciding what an option reads like and
  in which order. Only values that are in use are offered, so no option can return an empty
  index. A pivot on a `MorphToMany` reads its options with the morph constraint: the pivot
  table is shared, and without it a vocabulary tagging several content types would offer the
  tags of one resource in the filter of another.

  The id-keyed filters are applied to the query — a pivot through `whereHas`, a foreign
  through a plain `where` — rather than to the fetched rows. Every other type keeps filtering
  on its rendered value, because a json key, an accessor or a checkbox only exists once the
  row is rendered.

  The option list does not look at `$active` or the treeview branch, so a value attached only
  to an inactive row is still offered. One query for the whole column is worth that: the old
  option list read the entire table again for every filterable column.

- **An index search no longer matches the json.** `title LIKE '%nl%'` searched the raw
  `{"nl": .., "en": ..}`, so searching an index for "nl" or "en" returned every row — they are
  keys of every value. A translatable attribute is now searched per language, values only.

  It searches all of them, not just the active one: the panel is the one place a site's
  languages sit side by side, and being in the Dutch panel is no reason to be unable to find a
  page by its English title.

- **`unique` validates translatable attributes again.** The rule named the column plainly, so
  it asked where `slug = 'over-ons'` while slug held `{"nl": "over-ons", ..}` — a json object
  never equals a string, so it matched nothing and every duplicate passed. Worse than a missing
  check: `HasSlug` then quietly appended a `-2` on save, leaving the editor neither warned nor
  given the slug they typed. Each language is unique in its own right; the rule now says so.

- **An index ordered by a translatable column now sorts by the text, not the json.** A
  translatable attribute is stored as `{"nl": "Aap", "en": "Ape"}`, and the index ordered by
  the column itself — so MySQL compared json objects rather than the text in them. Every row
  sorted equal: ordering by a title did nothing, and descending read exactly the same as
  ascending. Ordering now addresses the json path of the active locale, which the query
  builder turns into the driver's own accessor.

  Reported as descending being broken on text columns, which it looked like from the outside:
  a plain column (a name, an email) was never affected, and ascending on a translatable one
  was just as broken — only less obviously, since there was no order to be wrong about.

  It hid behind SQLite, which has no json type: the column is text, so ordering it compares
  the raw json string, which sorts by whichever locale spatie writes first — right by
  accident, as long as that is the locale you are reading. The suite says so where it can:
  only the active-locale case can fail on SQLite, and it does against the old code.

## [0.10.11] — 2026-07-16

### Fixed

- **An index only groups into letters when it is ordered by text.** The group header is the
  first character of the ordered value, which says something for a title and nothing for
  anything else: ordering by a date put one "2" over every row of this century, an id grouped
  by its leading digit, and a select column headed "1" and "2" over rows reading "Active" and
  "Inactive" — the index renders a select's label, not the value it would group by.

  The guard was a single exception, `type != 'number'`, which could never have covered the
  first two: `Attribute::$type` defaults to `'text'`, so an id — never given a type — was
  indistinguishable from a title. `Resource::indexGroupable()` now asks three things instead:
  what the attribute says it is, what it renders, and what the model casts the column to.
  `getCasts()` always carries the primary key, so an id is caught for being an int rather than
  for being called "id". A column that declares nothing and is cast to nothing still groups.

  `indexGroupChar()`'s `$attribute` parameter is now optional and no longer passed: the index
  handed it whatever the header loop had left in scope, never the ordered column, and the
  method reads that from `$this` anyway.

## [0.10.10] — 2026-07-16

### Added

- **`HasDocumentMeta::metaDescription()`** — the `description`, falling back to the `intro` a
  listed content item already carries as its card text, else `''`. Both fields are nullable, so
  an item with only an intro used to emit no meta/OG description at all, while its JSON-LD used
  the intro and ignored the description. One method now answers "the descriptive text of this
  record" for the layout, the structured data and the search excerpt alike.
  See [docs/template.md](docs/template.md#hasdocumentmeta).

### Fixed

- **`HasDocumentMeta` no longer throws on a partly translatable model.** It checked only whether
  a model had `getTranslation()`, then called it for every meta attribute — but a translatable
  model asked for an attribute outside its `$translatable` throws `AttributeIsNotTranslatable`.
  Attributes are now checked against the model's translatable set and read as plain attributes
  otherwise, making good on the trait's promise to work on any model.

## [0.10.9] — 2026-07-16

### Changed

- **Documented that content types are named in English**, whatever language the site speaks.
  The name is code — the class, the table, the `leap.content` key, the section name — and
  never a URL: an overview lives at the slug of the page whose section lists that type, and
  detail pages at `{that slug}/{item slug}`. Both are per locale and the editor's to change,
  so a Dutch site is `/berichten` and its English twin `/news` from one `News` model. A Dutch
  class name buys nothing a visitor can see, and costs `Str::plural()` its accuracy.
  See [docs/content-types.md](docs/content-types.md).

## [0.10.8] — 2026-07-16

### Fixed

- **An unprefixed URL now renders the locale that claims it.** `Leap::detectLocale()` only
  ever set a locale for a *prefixed* URL, so `/` was left at whatever `APP_LOCALE` said. On a
  site whose first `leap.locales` entry was `nl`, an `.env` carrying `APP_LOCALE=en` rendered
  `/` in English while every URL rule still treated `/` as the Dutch page: English answered
  on both `/` and `/en`, Dutch on nothing at all, and the language switcher, canonicals and
  sitemap all pointed at the wrong one. One line in an untracked file, and the whole URL
  structure was wrong — silently, and differently per environment.

  Which locale is unprefixed is declared by `leap.locales` in `config/leap.php`, which is
  deployed with the code; `detectLocale()` now applies it explicitly. `APP_LOCALE` is left to
  what it is for — the console, queues and mail — and can no longer reach the frontend's URLs.

  The `Route::leapLocalized()` macro was never affected: it attaches `SetLeapLocale` to every
  locale group, the default one included.

  This also makes the two settings mean separate things, and lets them disagree on purpose:
  `leap.locales` decides the site's URLs, `APP_LOCALE` decides the admin, console, queues and
  mail. An English admin on a Dutch site is now a supported setup rather than a broken one.
  See [docs/multilingual.md](docs/multilingual.md#leaplocales-vs-app_locale). (On a
  monolingual site nothing is prefixed, so `APP_LOCALE` still decides the language outright.)

## [0.10.7] — 2026-07-16

### Added

- **`NickDeKruijk\Leap\Traits\HasSections`.** The read side of the sections editor — media
  merged in, per-locale fields resolved to the current locale, sorted, `_first`/`_last`
  flags — now lives here instead of being copied into every project by `leap:template`.

  Everything it knows is this package's own: the shape `Attribute::sections()` stores, the
  `Mediable` rows uploads land in, the `_sort`/`_name` keys the editor adds, and
  `leap.locales`. Change the editor and this has to change with it, so keeping them apart
  meant a fix could not travel. It already cost something: the monolingual crash fixed in
  leap-template 0.10.4 never reached any site installed before it, because their copy was
  frozen. Sites only escaped by being multilingual, where the broken branch happened to be
  the right one.

  `leap-template` 0.10.8 stops shipping its stub; the models use this trait directly, as they
  already could have for `HasSlug` and `Classes\Video`.

## [0.10.6] — 2026-07-16

### Changed

- **The passkey login button only shows once a passkey exists.** On a fresh install the
  button could not work for anyone, and said so to no one: with an empty `passkeys` table
  the browser opens an empty picker, and the `NotAllowedError` from dismissing it is
  swallowed by `passkeys.js` — so the click did nothing at all. Registration lives behind
  the login (Profile), so hiding the button until the first passkey is registered locks
  nobody out; it appears by itself once someone has one.

  The check is deliberately global rather than per-account: keying it on the typed email
  would let anyone probe which accounts exist and which of them have a passkey. It costs
  one indexed `EXISTS` per login render. `leap.auth_passkeys.enabled` still switches
  passkeys off entirely, login *and* Profile.

### Removed

- **The `brick/math` requirement, and the `-W` it existed for.** Install with a plain
  `composer require nickdekruijk/leap` again. The require was never about using
  brick/math — Leap has no such code. It was there to pull brick/math into Composer's
  update whitelist so that a partial `composer require` was allowed to *downgrade* it to
  the `^0.17` the WebAuthn chain accepted; without that, Composer silently installed an
  ancient Leap instead of erroring (0.9.10), and `-W` was the documented workaround.

  That whole chain now accepts `brick/math` `^0.18`: `spomky-labs/cbor-php` 3.3.0,
  `web-auth/cose-lib` 4.6.0 and `spomky-labs/pki-framework` 1.5.0. Nothing caps it below
  what a fresh Laravel already locks, so no downgrade is ever needed and the require has
  nothing left to do. Verified against a fresh `laravel/framework` install: a plain
  `composer require` pulls in the current Leap with `brick/math` staying at 0.18.0.

## [0.10.5] — 2026-07-16

### Changed

- **Reverted 0.10.4's `brick/math` widening**, back to `^0.17`. It made no practical
  difference at the time — the WebAuthn chain still capped `brick/math` at `^0.17`
  regardless of what this package allowed. Superseded by 0.10.6, which drops the require
  altogether.

## [0.10.4] — 2026-07-16

### Changed

- **`brick/math` now accepts `^0.18`.** `spomky-labs/cbor-php` 3.3.0 lifted its `^0.17`
  cap, so the mirrored constraint here follows. Leap does not use brick/math in code: the
  require exists only to pull it into Composer's update whitelist, so that
  `composer require nickdekruijk/leap` is allowed to downgrade it to whatever the WebAuthn
  chain still accepts (see 0.9.10). The constraint's *value* barely matters while
  something downstream caps it lower — its presence is the point — but it must not be the
  thing capping everyone once nothing else does.

  Once no package in that chain caps `brick/math` any more, **drop the require entirely**
  rather than widening it again: it exists to permit a downgrade that would no longer
  happen. `composer why-not brick/math 0.18` says whether that day has come.

## [0.10.3] — 2026-07-15

### Removed

- **The "installed as a dev-only dependency" console warning** (added in 0.10.1). It
  called `Composer\InstalledVersions::isDevRequirement()`, which only exists on Composer
  2.2+, so on older Composer it threw "Call to undefined method" on every console command.
  A package policing how it was required is scope creep — the two-package install is
  documented in the README instead. The `composer-runtime-api` requirement stays (used by
  `InstalledVersions::getInstallPath()` for the passkey routes).

## [0.10.2] — 2026-07-15

### Fixed

- **`Media::dimensions()` on Laravel 13.** Laravel 13 ships a native `Illuminate\Image`
  bound to the same `image` container key that intervention/image-laravel's facade uses,
  so `Image::read()` resolved to Laravel's manager — whose Intervention bridge calls a
  method absent in intervention/image 3.x — and dimension detection returned null. Build
  the Intervention `ImageManager` directly (`Media::imageManager()`), bypassing the facade.

## [0.10.1] — 2026-07-15

### Added

- **Console warning when leap is installed as a dev-only dependency.** leap is the
  runtime admin panel, so it must be a normal (non-dev) requirement; if it is only a
  dev requirement, `composer install --no-dev` removes it and `/admin` vanishes in
  production. Detected via `Composer\InstalledVersions::isDevRequirement()` (adds a
  `composer-runtime-api: ^2.0` requirement); shown on the console only, and never while
  developing leap itself. *(Removed again in 0.10.3.)*

## [0.10.0] — 2026-07-15

A breaking release. See [docs/upgrading.md](docs/upgrading.md) for the migration steps.

### Added

- **Frontend content types (news / events / generic).** Models rendered as a card row: a
  teaser on a page, a filterable overview of their own, and a section-based detail page
  each. Registered in the new `config('leap.content')`, which drives routing, the Page
  editor's card-row sections, live search and `sitemap.xml`. Generate one with the new
  `php artisan leap:content <Name>` (three archetypes — news is chronological with a
  required `published_at`, event has date/time + `future()`/`past()` and a scheduled
  `published_at`, generic is hand-ordered). See [docs/content-types.md](docs/content-types.md).
- **Shared tag filter** (`app/Traits/HasTags.php` + a polymorphic, translatable `Tag`),
  opt-out with `leap:template --no-tags`. Cards fill their row's height, are one clickable
  link, and have hover/keyboard-focus states; detail pages carry JSON-LD.
- **`app/Leap/Concerns/ContentSections.php`** — the Page resource's section blocks,
  shared with every content type.

### Changed

- **`leap:template`/`leap:content` moved to the dev-only package
  `nickdekruijk/leap-template`** (`composer require --dev`). `leap:module` and `leap:user`
  stay in core. `leap:module` and `leap:content` now refuse to run on production without
  `--force`.
- **`sitemap.xml` and live search read `config('leap.content')`**; `leap.sitemap.models`
  is kept only for models outside that registry.

### Removed

- **The `highlights` section** (demo-only) — replaced by model-backed content types.
- **The page-tree cache** (`config('leap.cache')`, `PageController::flushPageCache()` and
  the `Page` cache-flush events). `getPages()` is memoized per request with `once()`;
  remove `LEAP_CACHE` from `.env`.

## [0.9.17] — 2026-07-14

### Fixed

- **`leap:template` wrote routes that fail Pint.** It added the catch-all and the sitemap
  route with the controller fully qualified inline, which `fully_qualified_strict_types`
  rejects — so every scaffolded project started out failing its own style check, on a file
  it never wrote. The controller is now imported and named plainly. Projects that already
  have the old form are recognised (the check matches the controller reference, not the
  whole line), so re-running the installer does not end up adding the routes twice.

## [0.9.16] — 2026-07-14

### Fixed

- **The template's test suite could not run on CI.** Minify's import paths are relative
  (`../resources/css/`), which only resolve when the working directory is `public/` — true
  for a web request, false for anything run through artisan — and `testing` sat in its
  `skip_environment`, so during tests it did not compile at all but pointed at
  `public/css/builds/app.css` and hoped it was there. Together that left the suite
  depending on a build left behind by an earlier browser request: green on a developer's
  machine, five hundred errors on a fresh checkout. Worse, tests that read the compiled
  CSS were checking whatever a dev build had produced rather than the sources in the
  repository. `leap:template` now installs a `config/minify.php` with absolute import
  paths that compiles during tests, and the layout refers to vendor assets by absolute
  path.

## [0.9.15] — 2026-07-14

### Added

- **Cookie consent** (`leap.consent`). Banner, cookie table, CSS and JS ship with the
  package rather than as template stubs: a fix to something that has to hold up legally
  should reach every site through `composer update`, not leave each one on its own frozen
  copy.

  - **Nothing loads before it is allowed.** Pages are cached, so the HTML is identical for
    everyone and never contains a tracker or an `<iframe>`. Anything needing permission
    sits in a `<template data-consent="…">`, which the browser parses but does not run —
    no script executes, no request goes out, not even for an external `src`. It is cloned
    into the page only once that category is granted. So an editor pastes GA4, Meta or
    Hotjar's own snippet into a `scripts_<category>` setting and it works unchanged; the
    template knows nothing about any of them.
  - **The registry is a manifest, not decoration.** Purpose and retention are declared by
    hand, because no scanner can tell you what a cookie is *for* — and that is exactly
    what a privacy statement must state. `leap::cookie-table` renders it on the privacy
    page, and a browser test measures the real site against it: **a cookie that turns up
    without being declared fails the build.** Adding a service changes the registry's
    fingerprint, which expires consent already given — it covered what was on the table at
    the time, and no longer does.
  - **Matomo** is supported directly, because its cookieless mode is worth having: with
    `requireCookieConsent` it measures every visitor without setting a cookie, so the
    cookie law is never triggered and the people who refuse still show up in the figures.
    Consent only switches its cookies on. Nothing else can do this.
  - **The banner is a bar, never a wall**: no backdrop, no focus trap, no scroll lock. A
    visitor who ignores it can use the whole site. Refusing is one click, exactly like
    accepting, and nothing is pre-ticked — a banner that holds the content hostage is a
    cookie wall, and consent given to be rid of a barrier is not freely given, which makes
    it worthless.
  - Switchable per project: `enabled`, `default` (`denied`/`granted`) and `granular`
    (per-category screen, or plain accept/refuse). `window.consent.has()` answers in every
    configuration, so gated code stays on one path whether a banner exists or not.

  The banner's markup, class names and `window.consent` are **public API** — projects
  style it from their own stylesheet, so renaming a class breaks their overrides.

- **Video section** in the frontend template. YouTube or Vimeo, told apart by the id —
  Vimeo's are numeric. Nothing third-party sits in the page: the player is built in the
  click handler, and the poster is fetched from the provider once and stored locally,
  because hotlinking it would call on YouTube on every page view — the very thing a
  click-to-load player exists to avoid. Behind the "embeds" consent category, with a
  two-click way out so refusing embeds site-wide does not mean never watching anything.

  The logic lives in `NickDeKruijk\Leap\Classes\Video`, with a thin `App\Support\Video`
  stub around it (the `HasSlug` pattern), because it carries a fair amount of hard-won
  knowledge: YouTube only has a maxresdefault poster for HD uploads, Vimeo will only tell
  you where its poster is through oEmbed, and Safari refuses to autoplay a cross-origin
  YouTube frame with sound no matter what — youtube.com instead of youtube-nocookie.com,
  playsinline and the IFrame API were all tried and all blocked. None of that is worth
  rediscovering per project.

- **Cookie overview section** for the privacy page, rendering `leap::cookie-table`.

- **`leap:template` links `public/storage`.** Leap stores media on the `public` disk and
  the template serves it from `/storage`. Without the link nothing an editor uploads
  renders, and the failure points the wrong way: the file is plainly there on disk, but
  `asset_resized()` reports the *original* as missing. It was only mentioned in the
  closing notes, which is not enough for something every image depends on.
- **`leap:template` keeps generated assets out of version control.** `public/css/builds`
  and `public/js/builds` are written on request by `nickdekruijk/minify` from the sources
  under `resources/`, and the resize cache is filled by `nickdekruijk/imageresize` — but
  nothing stopped a project from committing any of it. Every branch then carries rebuilt
  artifacts that conflict on merge, and a stale copy can mask a broken source. The command
  now adds them to `.gitignore` (skipping rules that are already there). They regenerate
  on the first request, directories and all.

### Changed

- **The template's resize route is now `resized`, not `media/resized`.** Nothing else ever
  lived under `media/`, so it was an empty wrapper — a leftover from an older admin system.
  Only the template's own `config/imageresize.php` changes; the `nickdekruijk/imageresize`
  default is untouched, so no existing project moves.

## [0.9.14] — 2026-07-13

### Changed

- **Submenus no longer fold inside the hamburger panel.** On a phone there is room to
  simply list a submenu under its parent, one step smaller — a dropdown inside an
  already-open panel is a tap for nothing. Desktop keeps the caret and the fold. Alpine
  writes `display:none` inline on a hidden submenu, which no stylesheet can override, so
  `navigation()` gained a reactive `isMobile` (via `matchMedia`, so it also survives a
  window resize) and the submenu shows on `subOpen || isMobile`.

### Fixed

- **A cramped navigation bar broke menu items in half** instead of giving way. With a
  wide logo and a long menu, items like "Over ons" wrapped onto two lines. Menu links are
  now `nowrap`, and the logo is what yields: it shrinks (capped with `max-height`, so it
  scales down proportionally rather than squashing) while the menu keeps its size. The new
  `--logo-min-width` token sets how small it may get before the hamburger should take over.

## [0.9.12] — 2026-07-13

### Added

- **Shrinking navigation bar in the frontend template** — the sticky bar now animates
  from `--nav-height` down to `--nav-height-compact` as soon as the page scrolls, and
  starts out compact on mobile, where there is no room for the tall state. A tall
  header reads well on arrival but wastes vertical space while reading. It reuses the
  `.scrolling` class Alpine already sets, so no new JavaScript. Both a text logo
  (`--logo-font-size` / `--logo-font-size-compact`) and an `<img>` logo
  (`--logo-height` / `--logo-height-compact`) shrink along with it; duration is
  `--nav-shrink-duration`. Unset the `*-compact` tokens for a bar of fixed height.

### Fixed

- **`leap:user` did not work non-interactively at all** (`--no-interaction`, CI, a
  provisioning script). It leaned on prompts that cannot be asked:
  - Without an e-mail argument it crashed with Prompts'
    `NonInteractiveValidationException` instead of saying what was missing.
  - With one, the password prompt came back blank, so it fell back to a randomly
    generated password — and never printed it. The account was created and immediately
    unreachable, since nothing stores that password in the clear.

  The command now prompts only when it is actually running interactively, always shows a
  generated password, and falls back to the e-mail's name part when no name is given. It
  also warns when the new user ends up without a role (the role prompt defaults to "no",
  leaving an account that sees nothing in the admin panel), and no longer crashes when no
  roles exist yet. The command had no tests; it has seven now.
- **`leap:module` generated a module PHP could not load.** The resource normally
  carries its model's basename (`App\Leap\Project` for `App\Models\Project`), and the
  generated file imported the model — colliding with the class it was declaring:
  *"Cannot redeclare class App\Leap\Project (previously declared as local import)"*.
  It also emitted `public $model = App\Models\Project::class` without a leading
  backslash, which resolves relative to `App\Leap`. The model is now referenced fully
  qualified and never imported. The command was effectively unusable for any model
  whose name is used as-is, i.e. the default. The existing test only asserted on the
  generated *source text*, so it never noticed; it now lints and loads the file.
- **In-page anchors no longer land under the navigation bar** in the frontend template:
  `scroll-margin-top` now uses the compact height, since a jump to an anchor always
  happens with the bar already shrunk.
- **The logo no longer disappears behind the open mobile menu.** `.nav-main-container`
  is a fixed panel pinned to the top of the viewport, so it covered the whole bar; the
  hamburger lifted itself above it but the logo did not. Longstanding, unrelated to the
  shrinking bar.

## [0.9.11] — 2026-07-12

### Added

- **`leap-development` Boost skill** (`resources/boost/skills/leap-development/SKILL.md`)
  — on-demand agent guidance covering resources/modules, the `Attribute` API, roles
  and permissions, multilingual editing, sections, the frontend template and AI
  features, with pointers into the package's `docs/` directory. Complements the
  existing always-on `resources/boost/guidelines/leap.blade.php`.

## [0.9.10] — 2026-07-12

### Fixed

- **`composer require nickdekruijk/leap` failed without `-W`.** `brick/math`
  wasn't a direct dependency, so on projects where it was already locked to a
  version newer than `spomky-labs/cbor-php` (pulled in via
  `laravel/passkeys` → `web-auth/webauthn-lib`) supports, Composer's partial
  update refused to touch it and the install failed. Declaring `brick/math`
  directly, capped to the range `cbor-php` accepts, puts it in the update
  whitelist so a plain `composer require` resolves it correctly.

## [0.9.9] — 2026-07-10

### Fixed

- **Disabled translate badge no longer hints at an interaction it doesn't have.**
  When AI translate has no provider/key configured, the per-field locale badge
  (e.g. `NL`) correctly went non-clickable, but still showed the `.leap-hint`
  hover color and the global `.leap :focus` blue outline ring — both borrowed
  from the enabled/clickable variant. Now only the tooltip reacts to
  hover/focus, matching the badge's actual (non-interactive) state.
- **`<x-responsive-image>` crashed on SVG media.** `asset_resized()` has no
  decode path for SVG (only bitmap formats); the component now serves SVGs as
  a plain `<img src>` (they're already infinitely scalable, no responsive
  breakpoints needed), branching on `Media::isBitmap()`.

## [0.9.8] — 2026-07-10

### Added

- **`Media::focusPosition()`** — the crop focus point set in the file manager
  (`meta['image_focus']`), as CSS-ready `{x, y}` percentages, or `null` when unset.
  Mirrors `Media::alt()`. Pairs with `object-fit: cover` and inline
  `object-position` to keep the focus point visible when an image is cropped by
  its container's aspect ratio.
- **`<x-responsive-image>` template component**
  (`resources/views/components/responsive-image.blade.php`). Consolidates the
  `srcset`/`sizes`/`alt`/dimensions/focus-point boilerplate that was duplicated
  across the section views (`default`, `slide`, `highlights`) into one component;
  those views now use it. Uses `Media::alt()` and the new `focusPosition()`
  automatically — a focus point set in the admin now actually shows up on the
  frontend, which no section view previously read. See
  [docs/template.md](docs/template.md#x-responsive-image).

## [0.9.7] — 2026-07-10

### Changed

- **Filemanager: rename and alt-text moved into the always-visible button bar.**
  Rename was a small pencil icon next to a deceptively-clickable filename; alt-text
  was only reachable by hovering the image. Both are now `Rename file` / `Set alt
  text` buttons in the top bar next to Close/Delete (single file selected only).
  Focus-point and crop stay on the image itself — they're inherently "click a point
  on the image" actions. The filename in the stats panel is now plain text.
- **`leap.filemanager.image_crop_enabled` / `image_focus_enabled` accept `true`** as
  shorthand for "every bitmap format" (via the existing `isBitmap()` helper, which
  already excludes `svg`), enabled by default. The array form still works for finer
  control — e.g. excluding `gif` from crop (breaks animation) while keeping it for
  focus point.
- Added `:focus-within` alongside `:hover` on `.leap-focus-actions` so the
  focus-point/crop overlay buttons are visible to keyboard users tabbing onto them,
  not just mouse hover.

### Fixed

- **Filemanager: selected folder/file row lost its teal highlight**, rendering as
  near-invisible white-on-white text instead. Regression from the 0.9.5 CSS
  consolidation: `filemanager.css` (loaded last) unconditionally set
  `.leap-index-row TD { background-color: transparent }`, which tied in specificity
  with `leap.css`'s `.leap-index-row-selected TD` rule and won on source order,
  cancelling the selected-row background while `color: white` still applied.
  Scoped the transparent override to `.leap-index-row:not(.leap-index-row-selected)`
  so the two rules no longer compete regardless of file load order.

## [0.9.6] — 2026-07-10

### Changed

- **`HasSlug` now works on flat (non-tree) models.** Slug uniqueness was always
  scoped to a `parent` column, which threw on models without one. It is now scoped
  to siblings only when a sibling column exists — auto-detected as `parent` via the
  new `slugSiblingColumn()` (override to use a different column, or return `null`
  for global uniqueness). Page trees are unchanged; standalone models (services,
  stories, blog posts) can now use `HasSlug` for per-locale slug generation too.

## [0.9.5] — 2026-07-10

### Changed

- **Panel CSS rewritten from SCSS to plain CSS, and consolidated from 12 files to 3.**
  `resources/css/*.scss` → `leap.css` (core admin chrome), `filemanager.css`,
  `editor.css`. Colors are now CSS custom properties (`--leap-*`) alongside the
  existing spacing tokens, so host apps re-theme by overriding variables instead of
  overriding selectors — no recompile needed. Shared components like `.leap-button`
  now carry a real default background via `--leap-button-bg`/`--leap-button-bg-hover`
  instead of being re-styled in multiple files per context.
- `AssetController::css()` no longer compiles with ScssPhp — it concatenates the
  (now plain) CSS files directly. `nickdekruijk/minify` (and its transitive
  `scssphp/scssphp`) is no longer a leap-core dependency; it moved to `suggest` and
  is offered/installed only for the scaffolded frontend template, which still uses
  it for its own SCSS/JS.
- The Open Sans `@import url(...)` moved out of the compiled CSS into a `<link>` tag
  in the admin layout `<head>` (native `@import` must precede all other rules, which
  file concatenation no longer guarantees).

### Breaking

- The per-file host CSS override path (`resources/css/leap/<file>.scss`) now expects
  the new filenames (`leap.css`, `filemanager.css`, `editor.css`) — a host overriding
  the old per-feature `.scss` files (e.g. `nav.scss`, `forms.scss`, `login.scss`)
  needs to migrate that override to the consolidated files.
- If `nickdekruijk/minify` was relied upon transitively through `nickdekruijk/leap`
  outside of the template, add it to the host's own `composer.json`.

## [0.9.4] — 2026-07-10

### Fixed

- Test suite only: `HasLocaleRoutingTest` refreshes the router's name lookup after
  registering routes so `route()` resolves them without a preceding request,
  fixing a failure under `--prefer-lowest` (Laravel 12). No shipped code changed
  from 0.9.3.

## [0.9.3] — 2026-07-10

### Added

- **Reusable multilingual routing/SEO building blocks.** The locale-aware
  frontend logic that used to live only in the template stub is now part of the
  package, so projects with content types outside the page tree (e.g. services,
  stories, blogs on their own routes) get the same behaviour without copying it:
  - `Leap::localeDefault()`, `Leap::localePrefix()` and `Leap::detectLocale()` —
    one source of truth for the default locale, the `/xx` URL prefix rule and
    stripping a leading locale segment. The template `PageController` now uses
    these instead of its own private copies (behaviour unchanged).
  - `Middleware\SetLeapLocale` and the `Route::leapLocalized()` macro — register
    a frontend route once and get one group per configured locale, each with the
    right prefix (default locale unprefixed) and the request locale applied per
    request (never at route-registration time). The URL segment can differ per
    locale (e.g. `diensten` in nl, `services` in en).
  - `Traits\HasLocaleRouting` — per-locale URLs (`localeUrls()` / `localeUrl()`)
    and a default `Sitemapable` implementation for a flat translatable model
    whose routes follow the macro's `<name>.<locale>` naming.
- **Pluggable sitemap.** `Contracts\Sitemapable` plus `Classes\Sitemap` and the
  new `leap.sitemap.models` config let any model contribute entries to
  `sitemap.xml`; the helper merges them (skipping missing/non-Sitemapable
  classes). The template's `Page` implements it and the sitemap route falls back
  to a page-tree-only sitemap when no models are configured, so existing sites
  are unaffected.
- **`Section::translatableOnly()` / `translatableExcept()`.** Mark section
  sub-fields translatable in bulk. `translatableOnly('head', 'body')` is the
  explicit, safe form; `translatableExcept()` auto-marks only textual fields
  (text/textarea/rich-text) and skips switches, media, selects, dates, etc.,
  reducing the chance of forgetting a field. Individual `Attribute::translatable()`
  calls are unchanged.
- **`Traits\HasSlug` and `Traits\HasDocumentMeta` moved into the package.** The
  per-locale slug generation and the `documentTitle()` / `ogImageUrl()` head
  metadata are now package traits (fixable via `composer update`). The template
  ships a thin `App\Traits\HasSlug` wrapper so the application namespace is
  stable, and `HasDocumentMeta` degrades gracefully on models without
  media/sections.

## [0.9.2] — 2026-07-10

### Added

- `leap:module` artisan command: generates a resource from an existing Eloquent model,
  detecting field types, required/unique/sortable, foreign keys, enums, `$active` and
  `$orderBy` from the model's schema and casts. Re-running against an existing module
  merges in only the new columns instead of overwriting hand-written attributes.

### Fixed

- Template's `sitemap.xml` is now multilingual: every page gets one `<url>` entry per
  locale it has a routable slug translation for (cascading from its parent chain), each
  with `<xhtml:link>` hreflang alternates — matching the language-switcher already
  rendered in the page head. Monolingual sites are unaffected.

## [0.9.1] — 2026-07-10

### Fixed

- Correct the dependency constraints: require **PHP ^8.3** (runtime deps and the typed
  constants need it) and raise **laravel/fortify to ^1.31**, the floor that has
  `Fortify::currentEncrypter()` used by the 2FA flow.
- Test on Laravel 13 too: widen the dev tooling to Testbench `^10|^11` and PHPUnit
  `^11|^12`, and run the CI matrix as PHP 8.3–8.4 × Laravel 12/13. (PHP 8.2 is dropped —
  runtime deps require 8.3.) Fixed one enrollment test whose expected value only matched
  under PHPUnit 11's loose comparison.

## [0.9.0] — 2026-07-10

Release candidate for 1.0.0, tagged for real-world testing before the stable release. The
public fluent API (`Attribute`, `Section`, `Module`, `Resource`) is stabilising and treated
as frozen; the 1.0.0 tag will make that guarantee binding under semver. As a 0.x release,
semver still allows adjustments if testing surfaces something.

**Stability:** semver covers the module DSL you write — the fluent builders on
`Attribute`/`Section` and the `Module`/`Resource` classes you extend (their properties
and overridable methods). Methods marked `@internal` are Leap's own rendering/plumbing
that happen to be `public` (PHP has no package-private); they are **not** part of the
supported API and may change in a minor release. Don't call them from application code.

### Added

- **Multilingual content editing.** Set `leap.locales` to an associative array
  (e.g. `['nl' => 'Nederlands', 'en' => 'English']`) to edit translatable fields
  per locale in the admin. The editor shows a language switcher in the button bar
  (abbreviated tabs for up to three locales, a dropdown for four or more), a
  per-field locale badge, and
  validates the default locale as required with the others optional. Gated on
  `leap.locales`: when it is `null` (the default) behaviour is byte-for-byte
  identical to before. Mark section sub-fields with `Attribute::translatable()`;
  top-level fields derive translatability from the model's `$translatable`.
  Legacy monolingual values (plain strings from before a field became
  translatable) are wrapped into the default locale on load, so upgrading a
  record preserves its content instead of overwriting it on the first save.
- **AI content assistance.** With an AI provider configured under `leap.ai`
  (Gemini, Claude, OpenAI, or DeepL for translation), the admin can generate
  image **alt texts** per locale in the file manager and **translate** editor
  content into the active locale — per field or all fields at once (including
  section sub-fields), with an empty-only or overwrite scope. HTML markup is
  preserved, slug fields stay slugified, and results fill the form for review
  (nothing is saved automatically). Disabled by default; each task picks its own
  provider and model, and calls are per-user rate-limited and time-bounded. See
  [docs/ai.md](docs/ai.md).
- **Lazy click-to-edit rich-text.** Rich-text fields can show their rendered
  HTML as a preview and only initialize TinyMCE when clicked (torn down again on
  save), so editors with many rich-text sections open fast. Toggled by
  `leap.tinymce.lazy` (top-level fields, default off) and
  `leap.tinymce.lazy_sections` (section fields, default on).
- **`Attribute::slugFrom('source')`.** Declared on the slug field — the slug-field
  form of the slug relationship, mirroring `slugify()` (which declares the same thing
  on the source field). The source field is made live so the slug placeholder updates
  as you type. Works per locale.
- **`Attribute::label()`, `placeholder()` and `hint()` accept a per-locale array**
  (e.g. `->label(['nl' => 'Titel', 'en' => 'Title'])`), resolved to the current
  locale. `hint()` renders as an `(i)` tooltip next to the field label.
- **`Leap::context()` / `LeapContext`** — a request-scoped store for the active
  module, permission map and role name.
- **`leap.cache`** config option (default on). The frontend template caches its
  page tree and invalidates automatically on page save/delete.
- **`leap:template --diff`** reports how a project's template files differ from
  the current stubs without changing anything.
- Frontend template modernised: self-contained `slide`/`default`/`highlights`/
  `cta`/`quote` sections with optional per-section background photos, a carousel,
  a keyboard-accessible horizontal scroller, locale-aware live search (title,
  description and section content matched against the active locale only), an
  admin-editable
  footer, per-page SEO meta (Open Graph, Twitter, canonical, hreflang) and a
  `sitemap.xml`. Bilingual (nl+en) out of the box, per project switchable.
- Template ships `public/css/tinymce.css` and `leap:template` points
  `leap.tinymce.content_css` at it, so rich-text in the editor is styled like the
  frontend (buttons, headings, links). The seeded homepage now demonstrates every
  section layout (all `default` image positions, quote, cta, slider, highlights).
- `App\Traits\HasSlug` for the template: per-locale, sibling-and-locale-unique
  slugs, with `/` reserved for the homepage.
- **Responsive images (frontend template).** Section images and background photos are
  served through `nickdekruijk/imageresize`: `config/imageresize.php` (shipped by
  `leap:template`) defines width presets (600–2560) and the views emit `srcset`/`sizes`;
  full-bleed backgrounds are lazy `<img>` elements. Leap caches each image's intrinsic
  dimensions in `media.meta` via `Media::dimensions()`, so the section `<img>` carries
  `width`/`height` and reserves the correct box (no layout shift, no cropping). Requires
  `php artisan storage:link`.
- **Per-section "dark background" toggle** in the template's `default`/`highlights`/`cta`/
  `quote` sections — white text with the background photo (a legibility overlay) or a
  gradient fallback — plus a text-only image position.

### Changed

- Request-scoped state (active module, permissions, role) moved from Laravel's
  `Context` hidden keys to the scoped `LeapContext` service, so it no longer
  leaks into queued jobs or logs. **Backward compatible:** the old
  `leap.module` / `leap.permissions` / `leap.role.name` Context keys are still
  mirrored throughout 1.x (see Deprecated).
- The frontend template's homepage is the page whose slug is `/`
  (order-independent), and no longer also reachable at `/home`.

### Deprecated

- The `Context` hidden keys `leap.module`, `leap.permissions` and
  `leap.role.name` are mirrored for backward compatibility only and will be
  removed in 2.0. Read them through `Leap::context()` instead.

### Fixed

- Logging no longer writes a `user_id` for a session that points at a user who no
  longer exists (which could hit the `leap_logs` foreign key after a
  `migrate:refresh`). The user is resolved through the auth provider and stored as
  `null` when gone.

### Security

- **File manager uploads are re-validated server-side.** `$uploads` is a public
  (client-controllable) Livewire property, so the extension/size checks in
  `uploadStart` and the target path could be bypassed by setting the array directly
  (`error=false`, a forged name/path) and calling `uploadDone` — writing an
  arbitrary-named file anywhere on the disk with only `create` permission. `uploadDone`
  now re-checks the allow-list and size against the actual file and rebuilds the target
  directory from the open folders.

### Notes on upgrading

- Template/stub changes only apply when you re-run `php artisan leap:template`;
  existing projects are unaffected by `composer update` alone. Use
  `leap:template --diff` first to preview drift.
- Enabling `leap.cache` is safe everywhere because page edits invalidate it;
  disable with `LEAP_CACHE=false` or clear with `php artisan cache:clear`.
- Supported runtimes: PHP 8.3–8.4, Laravel 12/13, Livewire 3/4.

## [0.3.2] and earlier

See the Git history for pre-1.0 changes.
