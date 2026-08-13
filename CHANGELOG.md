# Changelog

All notable changes to `nickdekruijk/leap` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.10.0] — 2026-08-13

### Added

- **A preview button in the editor.** It opens the frontend of the record being edited, without
  looking its address up first, and it works on the records that have no address at all: one
  that is switched off, one that is not published yet, one written in a language
  `leap.locales_published` leaves out. The comment under that setting has promised this since
  it was added; here it is.

  A preview is not a public link. It answers to `Leap::validatePermission('read')` on the module
  the record belongs to, the same check as opening that module, and 404 rather than 403 for the
  same reason `Module::boot()` gives that answer. Nothing about it widens what the frontend
  serves: the record is fetched by id, so no scope was relaxed anywhere, and a page that is
  switched off still answers 404 on its own URL while its preview is open. The response carries
  `X-Robots-Tag: noindex, nofollow, noarchive` and `Cache-Control: private, no-store`.

  The application does the rendering, because only it knows how one of its records becomes a
  page. The module says how, by implementing the new `NickDeKruijk\Leap\Contracts\Previewable`:

  ```php
  class Page extends Resource implements Previewable
  {
      public function previewResponse(Model $record): View
      {
          return view('page', ['page' => $record]);
      }
  }
  ```

  On the module rather than the model, because that is where a project already describes this
  screen and the route is addressed by module slug, and it keeps the model free of it. Render
  through the same view the live route uses, or the preview will slowly stop resembling the
  page it previews. A module that does not implement it has no preview and no button, which is
  what an existing project sees until it adds the method. `leap-template` 2.3.0 ships it on
  the `Page` module and the generated content modules. `Leap::isPreview()`, `Leap::preview()` and
  `Leap::previewIsUnsaved()` are there for a page that wants to say it is a preview; they
  change no query.

  What you see is the form as it stands, not the last save: the button stashes the editor's
  values in your own session and the preview writes them onto the record without saving it,
  through the same code saving uses. Images and linked records are the exception, because those
  exist only once they are written, so those come from the saved version, and
  `Leap::previewIsUnsaved()` is how a page can say so.

  The button is what updates a preview. It stashes on the click and reuses one named tab, so
  clicking it again refreshes the page you are looking at. Reloading that tab yourself repeats
  the stash instead, showing the form as it was at the last click.

  Nothing to configure: the contract is the switch, and a stash expires after half an hour so a
  tab left open overnight shows the record rather than yesterday's typing. The stash holds the
  form as it is, sections and all, which the `cookie` session driver is too small for.

- **The editor warns before unsaved changes are thrown away.** Clicking another row, starting a
  new record or leaving the page asks first. It was silent, and closing an editor by accident
  cost whatever was in it.

  Whether there is anything to lose is answered on the server, because most of what changes an
  editor never reaches the browser as a typed character: media picked or reordered, a section
  added, a pivot toggled, a translation filled in by the AI. The browser adds what the server
  cannot see yet, typing that has not been sent, and rich text reports itself on change, since
  TinyMCE lives in its own iframe and hands its content over on blur.

  It asks only when there is really something to lose. "Something was typed" is not "something
  is different", so every way out that can wait for an answer (the close button, escape, another
  row, a new record) flushes the form to the server first and drops the question if
  nothing changed after all. Only leaving the page cannot wait for that, so there it errs
  towards asking.

### Changed

- **`x-leap::button` passes its attributes through on a link.** The `href` branch dropped them,
  so `target` and `rel` could not be set, and it always added `wire:navigate`, right inside the
  panel, wrong for a link that leaves it. The new `navigate` prop defaults to true, so nothing
  changes for existing callers.

## [1.9.0] — 2026-08-13

### Fixed

- **Media links no longer outlive the model carrying them.** `HasMedia` had no delete hook at
  all, so hard-deleting a record left its rows in `leap_mediables` behind with a `mediable_id`
  pointing at nothing. Two things followed. The file manager decides whether a file is still
  in use by counting those rows, so one orphan made that file undeletable forever, and the
  editor saw `media_in_use` with no way to find out whose link it was, because that record no
  longer existed. And ids restart at 1 after a `migrate:fresh`, so the next record to be given
  number 12 inherited the pictures of the one that had it before: 566 orphaned rows on one site
  after a single reimport.

  A model now takes its links along when it goes, but only when it really goes. Nearly every
  content model soft deletes, and a deleted record can be restored, so detaching on a plain
  `delete()` would empty the gallery of a record that is coming back. The links go on a
  `forceDelete()`, and on a plain `delete()` of a model that does not soft delete at all.

  The Media row itself is left standing on purpose: the file is still on disk, and a media row
  with no links left is exactly what the file manager needs in order to be allowed to delete
  it, which is the thing that did not work before.

  A project that relied on links surviving a hard delete — an import that deletes a record and
  writes it back under the same id — has to attach the media again.

### Added

- **`php artisan leap:media`**, for the links deleting a model never saw: everything left
  behind before this release, and what happens out of leap's sight — a mass
  `Model::where(...)->delete()`, a truncate, an import that renumbers. It reports by default
  and deletes with `--prune`, with `--dry-run` in between.

  The only question it asks is whether the record is still there, and it asks without a single
  scope: a soft deleted record counts as in use, and so does one hidden behind a project's own
  global scope. Media rows are never touched. A `mediable_type` naming a class the application
  does not have is reported and kept, because a renamed or moved model reads exactly like a
  deleted one; `--unknown` prunes those too, once you are sure.

- **`HasMedia::detachAllMedia()`**, the same cleanup by hand, for the deletes model events
  cannot reach.

- **`leap.locales_published`, the locales a visitor is served.** `leap.locales` decides what an
  editor can write, which is a different question from what the site publishes: a translation is
  usually entered long before it is finished, and until it is done it should be reachable from the
  admin and from nowhere else. `locales_published` names the subset that gets URLs. A prefix that
  is configured but not published falls through to a page lookup and 404s, `Leap::localeDefault()`
  answers with the first published locale instead of the first configured one, and a language the
  site does not serve yet cannot turn up in a sitemap or an hreflang set. Left at `null` it means
  every configured locale, so nothing changes for a project that does not set it.

### Changed

- **The focus point says what it does.** Its label in the file manager read "Set focus
  point", which editors reasonably take to mean "this part comes to the middle". It does
  not: `object-position` aligns the chosen point of the image with the same point of the
  frame, so a focus at 20% ends up 20% down the frame, and only 50% centres, by coincidence
  of being the midpoint.

  That behaviour is the useful one and stays: a point near an edge stays near that edge, so
  the subject is never pushed out of frame and no empty band appears. Centring a point
  exactly is not possible in CSS regardless, since it depends on the height of the frame,
  which only the browser knows at render time. So the wording changed rather than the
  behaviour, to "keep this part in view when the image is cropped", and
  `Media::focusPosition()` now spells the same out for whoever reads the code first.

## [1.8.1] — 2026-08-13

### Fixed

- **A section keeps rendering after a language is dropped from `leap.locales`.** A stored
  translation set was only recognised when *every* one of its keys was a configured
  locale, so removing a language (launching with one, say) made values written before that
  fail the test. `Leap::localize()` was then skipped and the raw array reached the view,
  where `htmlspecialchars()` refuses it. Sections feed the navigation too, so this was a
  500 on every page rather than one broken section.

  `HasSections` now treats a value as a translation set when at least one key is a
  configured locale and every key reads as a locale code. `{"en": "…", "nl": "…"}` on an
  English-only site is a translation set whose Dutch entry is ignored, and a data array
  (`{"width": …, "height": …}`) still is not, because none of its keys is a locale. The
  shape check on every key keeps a json field that happens to carry a locale-shaped key
  (`{"columns": 3, "nl": "…"}`) out of it as well, which is what the editor side already
  assumed. A monolingual site (`leap.locales` null) is untouched.

  The lower bound is deliberate: a value holding only dropped locales (`{"nl": "…"}` on an
  English-only site) has no configured locale among its keys and is left as it is, because
  nothing separates it from a data array whose keys happen to be two or three lowercase
  letters, such as `{"id": …, "url": …}`.

- **A gallery comes back in the order it was sorted in.** `HasMedia::media()` ordered by
  `mediable_attribute` alone and did not even load the pivot's `sort`, so within one
  attribute the order was whatever the database returned, in practice by media id. That is
  the same thing right up until two models share a file: media rows are keyed on the file's
  sha256, so a photo already used by an older model keeps that model's lower id and floats
  to the front. Since a frontend card shows a model's first image, a gallery that merely
  looked shuffled put the wrong picture on every overview listing it. The relation now
  loads `sort` and orders by it, with `media_id` breaking a tie so equal sorts do not
  reshuffle between requests.

- **An unknown width no longer costs an element its `<picture>`.** `ImageUrl::sources()`
  read the offered formats from the first rung of the ladder only. A caller may pass a
  width the project does not have, a 300 where `leap.images.widths` starts at 600, and
  `srcset()` skips those without complaint, but `ImagePreset::find()` returned null for
  that same rung, "no formats at all" followed, and the component fell back to a bare
  `<img>` in the default encoding: no `<source>`, no AVIF, and nothing anywhere reporting
  it. The formats now come from the first rung that resolves, so the two halves agree on
  being tolerant. A ladder holding nothing recognisable still offers no sources, because
  there is no preset to read an honest answer from.

## [1.8.0] — 2026-08-12

### Added

- **The cookie table names the session cookie instead of describing it.** It was declared
  as `*-session`, which is a pattern, not a name: a visitor who opens their browser to
  check finds `acme-session` and no row that matches it. The registry now declares
  `:session` and `Consent::cookies()` fills in `config('session.cookie')` when it is read,
  so the table shows the name that is really there — including on a site that renamed it.
  The placeholder is resolved at read time because config files load alphabetically and
  `leap.php` is parsed before `session.php` exists. A site with a published
  `config/leap.php` needs to change nothing: `*-session` resolves the same way, so it gets
  the real name too.

  `ConsentCookieDeclarationTest` is new and holds the registry to that: it walks the
  `Set-Cookie` headers of a request through the `web` middleware and fails on any cookie
  the server sets without declaring. Cookies written by JavaScript are invisible to it, so
  a site that loads Matomo or GA4 still checks that half in its own browser suite.

- **The table explains its asterisks.** `_pk_id*` stays a wildcard, because Matomo really
  does hang a varying part on the end, but the table now renders one sentence saying what
  the asterisk stands for (`leap::consent.wildcard_note`, nl and en). It appears only when
  a declared name contains one, so a site with fixed names gets no footnote about a
  character it does not show.

### Fixed

- **Renotating the session cookie no longer expires everyone's consent.** The fingerprint
  that expires a stored choice now folds the session cookie in under a fixed token instead
  of its name, so `*-session`, `:session` and the resolved name all hash the same. It is
  the same cookie either way, and a visitor has nothing to say about how it is spelled.
  Adding a service still changes the fingerprint, which is what it is for.

## [1.7.0] — 2026-08-12

### Added

- **`accept_all`: the accept button reads differently once the preferences panel is open.**
  Closed, the button is the only way to say yes and a project is free to shorten its label.
  Open, it stands next to "save choice", and the difference between the two is the whole
  reason the panel exists: one grants everything regardless of the switches, the other grants
  what the switches say. "Toestaan" and "Keuze opslaan" side by side do not tell anyone that.

  Both keys ship, reading the same out of the box, so nothing changes for a site that upgrades
  and leaves the labels alone. The point is the site that shortens `accept`: a translation
  override falls through to the package for the keys it does not define, so the panel keeps
  saying "Alles accepteren" without anyone having to think of it. The state you were not
  looking at fixes itself, which is the state where this goes wrong.

  The two labels are two `<span>`s in the same button, switched on `settings`, and only
  rendered when `granular` is on — without the panel there is no second state to tell apart.
  The one that shows first carries no `x-cloak`: `settings` starts false, so cloaking the
  closed label would leave the button blank until Alpine boots.

## [1.6.1] — 2026-08-10

### Fixed

- **The driver fix in 1.6.0 read a config key that does not exist.** It asked for
  `config('images.driver')`; Laravel's is `config('images.default')`. That branch therefore
  never fired, and `IMAGE_DRIVER=imagick` went on being ignored exactly as before — the thing
  1.6.0 said it had fixed. Nothing regressed, but nothing improved either.

  The order between the two keys is reversed with it, and that is the more important half.
  `config('images.default')` answers `gd` on an app that never chose one, since that is the
  framework's own default, and it cannot be asked whether anyone meant it. Reading it first
  would have moved every site with a published `config/image.php` from Imagick to GD, taking
  the avif tier and the webp `effort` setting with it. So the older key wins now: it can only
  be there on purpose. A project that wants `.env` to decide deletes `config/image.php`.

## [1.6.0] — 2026-08-10

### Changed

- **`intervention/image-laravel` is gone; this package now depends on `intervention/image ^4.0`
  directly.** It was only ever a supplier: the facade and the container binding it registers are
  used nowhere here, and `Media::imageManager()` already built its own manager to step around the
  `image` container key that Laravel 13's own image feature claims as well.

  Keeping it was blocking every host app. `intervention/image-laravel` pins
  `intervention/image ^3.11`, `Illuminate\Image` requires `^4.0`, so a site on Laravel 13 could not
  install Laravel's `Image` facade for its own code as long as it had Leap. It can now.

  Laravel 12 is unaffected — `intervention/image ^4` is framework-agnostic and wants PHP 8.3, which
  this package already required.

  A project that used the `Image::` facade or `config/image.php` from `intervention/image-laravel`
  has to require that package itself, or move to Laravel's `Image` facade.

### Fixed

- **`IMAGE_DRIVER=imagick` was ignored.** The driver was read from `config('image.driver')` only —
  intervention/image-laravel's key, holding a driver classname. Laravel 13 reads
  `config('images.driver')`, the plain names `gd` and `imagick`, from `IMAGE_DRIVER`. A site that
  set that got Laravel's Image on Imagick and this package still silently on GD: a weaker avif
  encoder, and `effort` quietly doing nothing, since libwebp's `method` is only reachable through
  Imagick.

  `Media::imageDriver()` now reads `images.driver` first and falls back to `image.driver`, so one
  setting covers both. `ImageResizer::supports()` memoizes on that resolved driver instead of on
  the raw config value, which was `null` on any app that never set the old key — one answer then
  stood for both drivers, exactly what the key was there to prevent.

## [1.5.3] — 2026-08-10

### Fixed

- **An original whose extension is not lowercase was a 404 on Linux.** `targetPath()`
  lowercased the source's extension when composing a copy's address, so
  `news/PHOTO.JPG` was offered as `news/PHOTO-750e0179.jpg.webp`.
  `parseTargetPath()` is the inverse and has only that address to work from, so the
  request went looking for `news/PHOTO.jpg`, which on a case sensitive filesystem is
  a different file — and one that is not there. The extension is now carried over
  exactly as the source spells it.

  Nobody saw this while developing: macOS does not tell the two names apart, so the
  original was found and the copy written. It only shows on the server.

  Copies already written under the lowercased name are orphaned by this, since the
  address changed. `leap:images --prune` takes them along; the new address is written
  on the next request either way.

- **Validation on blur came back on Livewire 4.** Fourteen inputs across login, profile,
  two factor, forgot-password and reset-password used `wire:model.blur`, which in Livewire 4
  only syncs client-side: no request, so no `updated()`, so no per-field validation until
  submit. They now use `wire:model.live.blur`. Identical behaviour on Livewire 3, where
  `isLive` and `onBlur` are separate flags and the setter only commits when neither `lazy`
  nor `blur` is set — so this is the same code path either version resolves to.

## [1.5.2] — 2026-08-10

### Fixed

- **`leap:images --prune` deleted good copies when artisan ran on a PHP the site does not use.**
  Found on a live server: a site on PHP 8.5 with Imagick, serving avif to every visitor, and a
  default `php` still on 8.4 without the extension. One prune, and the copies behind the lightbox
  were gone.

  Nothing was wrong with the files. `ImagePreset::format()` drops the formats the driver cannot
  encode, because offering a `<source>` no copy can ever be written for is worse than not offering
  it. With no encoder for any of them there is nothing left to drop to, so it answers "the source's
  own format", and `parseTargetPath()` then reads `photo-a1b2c3d4.jpg.webp` as a name this package
  would never have written. `isOrphan()` says so, in as many words, and prune believes it. The hash
  and the original are never even looked at.

  `--prune` and `--warm` now refuse to start when a preset offers formats and the driver can write
  none of them, and name the PHP binary they are running under:

  ```
  The image driver cannot encode avif or webp, which leap.images asks every preset for.
    Every copy already written that way then reads as a layout this package does not write:
    --prune would delete all of them and --warm would rewrite them at addresses nothing asks for.
    Almost always a command line running a different PHP than the site. The driver is
    imagick; check that this PHP has it: /usr/bin/php8.4
  ```

  Before the dry run, deliberately. A `--prune --dry-run` under the wrong binary reports how many
  files it would delete, which reads as an answer while the question was wrong.

  A preset offering one format is untouched: that is a plain string and it passes through whether
  the driver has the encoder or not, exactly as before.

  On Forge this is two settings in two places: the site's PHP version, and the server's CLI
  version. A scheduled job runs on whichever `php` resolves to, and `update-alternatives --display
  php` says which one that is. See docs/images.md.

## [1.5.1] — 2026-08-07

### Fixed

- **The image format tests asserted the machine they were written on.** Nothing in the package
  changed: `ImageResizer::supports()` probes because which formats a driver can write is a property
  of the build, and that is still what it does. The tests around it did not follow their own
  reasoning, naming avif and expecting GD to lack it. CI runs a GD that writes avif and an Imagick
  that does not, so five of them failed there while passing locally. They now use a format no build
  has, and the one test that does need a real encoder skips on the probe rather than on
  `Imagick::queryFormats()`, which lists avif on builds that then fail to encode it.

  The documentation said "GD has no avif encoder at all" in three places. It is a common
  configuration, not a rule, and the same CI disproves it. It now says how to find out instead, and
  says to ask over HTTP: `php artisan tinker` runs under the CLI binary, and CLI and PHP-FPM
  routinely load different extensions, so a server whose terminal reports no avif can be serving
  avif on every request.

## [1.5.0] — 2026-08-07

### Added

- **`format` now takes a list: AVIF and webp from one `<x-leap::responsive-image>`, with a fallback
  that still works.** A string is one encoding for everyone, as before. An array is an ordered offer,
  best first: the component wraps its `<img>` in a `<picture>` and gives each entry a `<source>`, so
  the browser takes the first type it recognises.

  ```php
  'format' => ['avif' => ['quality' => 55], 'webp' => []],
  ```

  Quality is per format, and wants to be: avif reaches the same picture at a markedly lower number
  than webp, and carrying webp's 80 over would make the avif copy the larger of the two: the whole
  point thrown away.

  Each format is the same preset asked for differently, `{preset}.{format}`, so a copy per format
  lands in a directory of its own. It stays a per-preset option, so a named preset may offer a
  different set from the widths around it, and the allowlist is that preset's own list.

  **A lone URL takes the last entry.** `$media->url()`, an og:image, a video poster: none of them can
  negotiate, and the list is best first, so the last is the most compatible. Handing a scraper avif
  because it was listed first is how a social preview goes blank.

  A second format costs nothing until something asks for it: a browser downloads exactly one
  `<source>` and a copy is written on first request, so the ladder a visitor cannot read is never
  generated.

  A format the active driver cannot encode is left out of the markup rather than offered and served
  broken; a `<picture>` commits to the first `type` it recognises and never falls back to the `<img>`.
  What the driver can do stays, so on GD `['avif', 'webp']` is simply a webp source and the fallback.
  Probed rather than assumed from a list: whether a build has an encoder is a property of the
  machine, not of the driver's name: this package's own CI turned out to run a GD that writes avif
  and an Imagick that does not, and Imagick::queryFormats() lists avif on builds that then fail to
  encode it. Check the machine that will serve the site rather than trusting a rule of thumb.

  A lone URL skips formats the driver cannot encode when picking its own, so a list of nothing but
  avif on GD resolves to the source format rather than to `.avif` addresses no copy can be written
  for. A lone `'format' => 'avif'` *string* is an explicit instruction and is left alone, but it also
  has no `<picture>` and so no fallback for browsers without avif, which is what the list form is for.

- **`fallback`: a per-preset option for what the `<img>` inside a `<picture>` gets.** An override on
  top of the preset itself, addressed as `{preset}.fallback`, so `width`, `height` and `fit` come
  along. That is the point of it being an override: a square preset whose fallback was a loose width
  would hand a legacy browser a different shape than every `<source>` above it, and the layout would
  jump on exactly the machines least able to cope. Narrow it with `width` where the full size is
  wasted on the few who land there.

  Its format defaults to the source's own, deliberately: forcing jpg would flatten every transparent
  png onto black, and avif and webp both carry alpha, so this is the only step that could lose it. It
  is also what Google Images indexes, since Google reads the `<img src>` and not a `srcset`.

  With `format` a plain string there is no `<picture>` and no fallback preset, and the component
  emits exactly the `<img>` and ladder it did before. Existing projects generate the same copies at
  the same URLs.

- **`leap.not_found_log`: a line in the log when a page is asked for that is not there.**
  Off by default — a missing page is not a fault of the application, and most 404s are a
  scanner working through a wordlist rather than anything to fix. Switch it on when you
  are chasing broken links, after a migration or while writing a redirect map.

  The line answers two questions: which link is broken and where does it live, and was
  this a visitor or a machine. The second one is why the anonymized IP and the user agent
  are on — a bare path cannot tell them apart, and that answer decides whether there is
  anything to fix at all.

  ```php
  'not_found_log' => [
      'enabled' => env('LEAP_NOT_FOUND_LOG', false),
      'channel' => env('LEAP_NOT_FOUND_LOG_CHANNEL'), // null = the default channel
      'level' => 'info',
      'throttle_minutes' => 60,
      'referer' => true,                 // the page that linked here
      'referer_query_string' => true,    // including its query string
      'ip_address' => true,              // log the visitor's IP
      'ip_address_anonymized' => true,   // with its last part taken off
      'user_agent' => true,              // log the user agent string
  ],
  ```

  The address is written as `198.51.100.xxx` — enough to tell one network from another,
  which is what a log is ever asked, and not enough to tell one person from another.
  Switching `ip_address_anonymized` off gives the whole thing, and is a decision worth
  making on purpose. The same key names as the `logging` block, with the same meaning;
  both now share one implementation, `Leap::anonymizeIp()`.

  The referer is kept whole, query string and all: `?page=3` says which page of a listing
  carried the dead link and `?utm_source=…` says the newsletter did. It is also nearly
  always one of your own URLs — browsers have defaulted to
  `strict-origin-when-cross-origin` for years, so a referer from somewhere else arrives as
  a bare origin with no path and no query at all. `referer_query_string => false` keeps
  only the path, for a site whose own URLs carry something it would rather not log.

  `channel` names a channel from `config/logging.php`, so these can go to a file of their
  own instead of into the middle of everything else.

  `throttle_minutes` is how long the same path stays quiet after it has been written once.
  Without it a scanner writes a line per guess: a log nobody can read, on a disk that
  fills at whatever rate a stranger chooses.

  Hung off the exception handler's `render()`, not `report()`. Symfony's `HttpException`
  is on Laravel's internal do-not-report list, so a report callback is never handed a 404
  at all — and taking it off that list to reach one would hand every 403 and every
  `abort()` to whatever else is reporting, Sentry included. The callback returns null, so
  the error page renders exactly as before.

  This lives here rather than in `leap-template` because `leap-template` is a dev
  dependency: its service provider is absent on the server, which is the only place a 404
  log is worth anything.

## [1.4.0] — 2026-08-07

### Changed

- **The consent banner works under Alpine's CSP build.** Its behaviour used to be an
  inline `x-data` object that called `window.consent` and `document.addEventListener`
  from inside the markup. Alpine's CSP build — the one a site needs in order to keep
  `'unsafe-eval'` out of its Content-Security-Policy — refuses both: method shorthand in
  an object literal is not in its grammar, and every value that sits on `globalThis` is
  rejected outright. On such a site the banner threw on load and never appeared, which
  means it also never asked.

  The behaviour moved into `resources/js/consent.js`, as `Alpine.data('leapConsent')`. It
  closes over the same `api` the file already builds, so it reaches for no global at all —
  the restriction is met rather than worked around, and there is one place that knows how
  consent is stored instead of two. Every other directive in the banner is unchanged.

  The "change your choice" button in the cookie table gets its own scope,
  `Alpine.data('leapConsentReopen')`. It used to borrow whatever `x-data` the host layout
  happened to put on `<body>` and call `window.consent.open()` through it — so on a page
  without such a wrapper it silently did nothing.

  **Nothing is required of a site that is not using the CSP build**: both components are
  ordinary `Alpine.data()` registrations and work the same under the standard build. A
  site that has published its own copy of `consent-banner.blade.php` keeps working too,
  since the old inline object still calls a `window.consent` that is still there — but it
  will not survive a move to the CSP build until it takes this change.

  Bundling `resources/js/consent.js` before Alpine, as the frontend template does, is
  still the better order — but it is no longer a requirement. `alpine:init` fires once and
  a listener added afterwards never hears it, so a bundle in the wrong order would have
  registered nothing and the banner would simply never have appeared, which looks exactly
  like a visitor who had already answered. The file now checks whether Alpine is already
  present and, if so, registers immediately and re-initialises the two elements that name
  these components.

## [1.3.1] — 2026-08-05

### Changed

- **`leap.images.defaults.lossless_from` is now empty**, where 1.3.0 shipped `['png']`.
  The reasoning behind that default — text in a screenshot turns to mush at quality 80 —
  was never measured. It is: against quality 80 at the same width, lossless webp is 5.4x
  the bytes for a screenshot and 8.5x for a photograph that happens to have been saved as
  a PNG. Nobody should pay that without asking for it. The setting stays, for the sites
  whose PNGs really are line art or UI: add `'png'` back and re-run
  `php artisan leap:images --prune --warm`.

## [1.3.0] — 2026-08-05

### Added

- **Resized images, with a cache that cannot go stale.** Leap now serves resized copies of
  the images on the filemanager disk: `<x-leap::responsive-image>` for a full srcset,
  `$media->url(1200)` for a single size, `Leap::image($path, 1200)` for an image with no
  Media row. Copies are generated on the first request and served straight off disk by the
  web server after that, so PHP runs once per URL and never again.
  Each URL carries the first eight characters of the file's sha256
  (`/img/1200/photos/office-a1b2c3d4.jpg.webp`). Replace an image with a different one
  under the same name and every URL pointing at it changes, so the browser, the CDN and
  the web server serving the file without asking all move on together. There is nothing to
  invalidate — only orphaned copies to sweep with `php artisan leap:images --prune`.
  This is what the separate `nickdekruijk/imageresize` package could not do: its cache path
  came from the file's name alone, so a replaced image kept serving the old one until
  someone deleted the whole directory by hand.
  Deleting, renaming or replacing an image through leap takes its copies along at that
  moment — the path and hash they were made under are known exactly there — so pruning is
  only for what happens out of leap's sight.
  **Off by default** (`leap.images.enabled`), because a site still running imageresize
  would otherwise have two packages answering overlapping routes. Nothing changes for an
  existing install until it is turned on: no route, no disk, no migration, no helper. See
  [docs/images.md](docs/images.md), including how to migrate off imageresize.
- **`php artisan leap:images`** — `--warm` to generate everything up front (a post-deploy
  step, since a fresh release directory starts with an empty cache), `--prune` to delete
  copies nothing points at, `--sync` to re-read files written from outside leap (an rsync,
  a deploy script, an import — nothing else re-reads the row those URLs are built from),
  `--clear` to start over, `--dry-run` on any of them.
- **`Media::syncFromDisk()`** — re-reads size, mime type and content hash and drops the
  cached dimensions. Anything that writes over an existing file should call it.
- **`$media->url` and `$media->srcset`** also read as properties, giving the file's own
  URL and the default srcset ladder. Not sugar: Eloquent resolves a property it has no
  attribute for by looking for a method of that name and insisting it return a
  relationship, so the new `url()`/`srcset()` methods would otherwise make reading
  `$media->url` throw where it used to be null.
- **`leap.filemanager.upload_replace`** — an upload with the name of a file that is
  already there still lands beside it as `name-1.jpg` by default. Set this to `true` and
  it writes over the old one instead, keeping the same Media row, so everything already
  pointing at that image shows the new one: "upload a new version" doing what it says.
  The numbering exists because a resized copy used to be addressed by file name alone,
  which made replacing a file something that could not be seen; the hash in the URL is
  what makes the choice safe to offer. Off by default all the same — writing over a file
  changes every page using it at once — and only for someone with update permission;
  anyone else gets the numbered copy.

### Fixed

- **Cropping an image over itself left the wrong dimensions behind.** The file manager
  updated the row's size, hash and history but not the cached `meta.width`/`meta.height`,
  and `dimensions()` returns those as soon as they are set. Every crop therefore left the
  frontend reserving the aspect ratio of the picture as it was before, for good. The crop
  now re-reads the file through `syncFromDisk()`.
- **A photo off a phone measured as landscape.** `Media::dimensions()` read the raw pixel
  size and ignored the EXIF orientation tag, so a portrait photo with `Orientation=6` was
  stored as 4000×3000 while every browser displayed it 3000×4000 — the `<img>` reserved a
  box of the wrong shape and the page jumped when the picture loaded. Dimensions and
  resized copies are both taken from the image the right way up now. Existing rows are
  corrected by `leap:images --warm`.

## [1.2.2] — 2026-08-04

### Fixed

- **Alt text can now be generated for a photo out of a camera.** The button failed on
  every image straight off a phone, camera or drone. `describe()` sent the file as it
  sat on disk, and providers cap an image at a few megabytes once base64 encoded —
  which adds a third to whatever the file already was. A 7 MB drone photo travelled as
  9.3 MB and was refused before anything was described.
  The copy that travels is now bounded by `leap.ai.alt_text.max_width`, default 1568
  pixels on its **longest side** — width and height both, because a portrait photo of
  6048 pixels is just as far over as a landscape one. That number is where the vision
  models resize server-side anyway, so everything above it was paid for and thrown
  away. It only ever scales down, so a small image passes through untouched, and the
  file on disk is not modified. The copy is sent as JPEG whatever the source was;
  transparency is of no consequence for describing a picture. Set it to `null` to send
  the original.
- **A failed alt text leaves a trace.** Both places that catch it — the file manager
  button and the automatic pass after generating an image — dropped the exception on
  the floor. That is right in the sense that a failed suggestion must not cost the
  upload or the image that was just paid for, but it left nothing to act on: a
  provider refusing an oversized image looked exactly like a missing API key. Both now
  `report()` first, so it lands wherever the project already sends its exceptions. The
  toast, the upload and the generated image are unchanged.

  **Note:** alt text still shows no price. `prompt()` discards the token usage the
  provider reports, and no rates ship for chat or vision models, so `cost()` has
  nothing to work with — only image generation is priced. Measuring a chat task is a
  separate change.

## [1.2.1] — 2026-08-04

### Fixed

- **Section fields are readable in the editor on a monolingual site.** With `leap.locales`
  unset, every section field showed *"[object Object]"* whenever the column held a
  per-locale array — which it does on any site seeded by `leap-template`, since the seeders
  ship every language. `editorLocales()` is empty without configured locales, so the field's
  `dataName` gets no locale and the input binds to the stored value itself. `HasSections`
  has resolved exactly this on the frontend for as long as it has existed, so a page
  rendered fine while its editor did not; the collapsed section title was the one exception,
  and the giveaway — it is the only place that goes through `Leap::localize()`
  unconditionally.
  It was destructive as well: the editor writes its data back unchanged, so the first
  keystroke in such a field replaced the whole array with a single string.
  The editor now resolves a leftover per-locale array to the site's locale, the same way the
  frontend does. Collapsing is gated on `leap.locales` rather than on `editorLocales()`:
  that one is also empty for a module whose model has no translatable columns, and
  collapsing there would throw away languages the site still serves. Only sub-fields marked
  `translatable()` are touched — an `Attribute::json()` value is an associative array too,
  and flattening it would destroy it.
  **Switching a site between one language and several keeps working in both directions and
  is now covered by tests.** Turning `leap.locales` on keeps the text a field already had
  as its default locale (that wrap was already there, but untested); turning it off resolves
  the array back to one string. Neither direction rewrites the column — the editor converts
  what it finds when a page is opened, and a page nobody edits keeps the shape it has.

## [1.2.0] — 2026-08-04

### Changed

- **`leap.ai.show_costs` now defaults to `false`.** The estimate and the amount are computed
  from the rates in `leap.ai.pricing`, never reported by the provider, so they exclude VAT
  and any free tier and will not match an invoice. Close enough to report on, not close
  enough to put in front of an editor who did not ask for it — so the panel stays quiet
  unless a project says otherwise, which is the right way round for a figure that can be
  wrong. Set it to `true` where the person generating is the person paying.
  `record_costs` stays `true`: nothing recomputes the amount afterwards, so turning that off
  loses what an image cost for good. A config that names `show_costs` explicitly is
  unaffected.

### Fixed

- **An index that lists a pivot attribute eager loads it.** Rendering a pivot column reads
  the relation off every row, so a resource that did not name it in `$with` paid one query
  per row — and on a project running `Model::preventLazyLoading()` (which the frontend
  template's own `AppServiceProvider` turns on locally) opening the index threw
  *"Attempted to lazy load [tags] on model […] but lazy loading is disabled"* instead.
  Which relations the index reads is something the resource already knows from its
  attributes, so it loads them itself rather than asking every resource to repeat it. A
  resource that does name the relation in `$with` keeps its own version, constraints and
  all.
- **A published config no longer hides keys that a later release added below the top level.**
  Laravel's `mergeConfigFrom` is a single `array_merge`, so a project's `config/leap.php`
  replaced whole sections: publishing before 1.1.0 meant `leap.ai.record_costs` read `null`
  rather than the `true` this package ships, and every future nested key would have arrived
  the same way — off, with nothing to notice. The package config is now merged in at every
  level, so a config only has to hold what it wants to differ.
  **Lists are deliberately exempt:** an array with numeric keys replaces its counterpart
  whole, so trimming `default_modules` leaves it trimmed and `'presets' => []` means none.
  Only arrays keyed by name are combined, and a value whose shape differs between the two
  is taken from the project as-is.
  Note the one case where this changes behaviour rather than fixing it: a section you
  *shortened* to turn something off is now filled back in from the package. Set the key
  explicitly instead of deleting it.
- **`leap.ai.image.max_width` follows the `null` it documents.** The code fell back to
  `1600` when the key was absent, while the published config and the 1.1.0 notes both say
  the model's own resolution is kept. A config that names a number is unaffected.

## [1.1.0] — 2026-08-04

### Added

- **Image generation takes named presets, so the editor picks the model.** `leap.ai.image.presets`
  maps a label to a model id with an optional `:quality` suffix
  (`'medium' => 'gpt-image-1-mini:medium'`). The provider follows from the model name, so one
  preset can run on Gemini and the next on OpenAI, and a preset whose provider has no api key is
  not offered. `max_width` and `jpeg_quality` stay global — they are post-processing the provider
  never sees.
  Two presets or more add a **Quality** select to the generate dialog, each option carrying its own
  price estimate; one preset leaves the dialog exactly as it was. Keys are free-form —
  `low`/`medium`/`high` are translated, anything else is shown as written. The picked preset
  travels as a key, never as a model name, so the browser cannot ask for a model the config does
  not offer. See [ai.md](docs/ai.md#image-presets).
- **The file manager says which images a model made.** Selecting a generated image puts an **A.I.**
  badge next to its name; clicking that unfolds the prompt it came from, the model that answered
  (and its quality) and what the call cost. Which files were generated is worth seeing at a glance,
  the rest only now and then, so the details stay folded away. It reads `meta['ai']` on the media
  row, so it works for images generated long before this release — and the cost line follows
  `leap.ai.show_costs` like everywhere else.
- **Costs can be hidden without losing them.** `leap.ai.show_costs` (default `true`) controls the
  estimate and the amount shown in the panel; `leap.ai.record_costs` (default `true`) controls
  whether the amount is kept in the media row's `meta['ai']['cost']`. Separate switches: a computed
  figure you would rather not show an editor is still worth having for reporting. The row now also
  records the quality the image was generated at.

### Changed

- **The generate dialog asks for a shape instead of an aspect ratio, and no longer crops.**
  Landscape, portrait and square are the three canvases the providers actually offer, so the
  request maps onto them exactly (OpenAI a canvas size, Gemini a 4:3 / 3:4 / 1:1 hint). The stored
  JPEG now keeps the proportions the model produced: cutting a strip off to force `16:9` threw away
  part of an image that had been paid for and approved in the preview. For the same reason
  **`leap.ai.image.max_width` now defaults to `null`** — the resolution the model answered with is
  kept, and how large an image is *displayed* is a frontend concern. Set a number to cap it on a
  site that serves the stored file straight into a page. Encoding to JPEG at `jpeg_quality` still
  happens; providers answer in PNG. `leap.ai.image.aspect_ratios` is gone
  from the published config and no longer read; a ratio string passed to `ImageGenerator::generate()`
  or `generateImage()` is reduced to its orientation, so existing calls keep working.
  `ImageGenerator::ratio()` is deprecated in favour of `ImageGenerator::orientation()`.
- **The file manager's generate button moved into the folder's own button row**, next to *New
  folder* and *Upload*, instead of sitting top right in the screen header. Generating puts a file
  in the folder that is open, so it belongs with the other two ways of doing that rather than
  among the screen-level actions.

### Deprecated

- **`leap.ai.image.provider`, `model` and `quality`.** They still decide when a config sets them
  (behaving as a single unnamed preset), so existing installs are unaffected, but `presets` replaces
  all three and they are scheduled for removal in **2.0**.

## [1.0.2] — 2026-07-24

### Fixed

- **Two modules claiming one slug now yield one module.** A slug is a module's identity —
  the navigation entry and the route name `leap.module.{slug}` — but nothing enforced
  that, so a duplicate produced two navigation items and two `Route::get()` calls under
  the same name. It surfaces the moment a package starts shipping a module a project
  already keeps its own copy of: `nickdekruijk/settings` 1.3.0 registers its own settings
  screen, and every project with an `app/Leap/Setting.php` then listed Settings twice.
  Modules are now keyed by slug, and because the `app/Leap/` scan runs after
  `leap.default_modules`, a project's own module replaces the one a package registered —
  which is the override a project means by keeping the file. Delete your copy to move to
  the package's version. Navigation items with `$slug = false` (Logout) register no route
  and are exempt. See [modules-and-resources.md](docs/modules-and-resources.md#discovery).

## [1.0.1] — 2026-07-23

### Added

- **PHP 8.5 is tested and supported.** The CI matrix gains it as a third runtime, so it
  now runs PHP 8.3/8.4/8.5 × Laravel 12/13 × prefer-lowest/prefer-stable.

### Changed

- **The documented runtimes read PHP 8.3–8.5.** `composer.json` already required
  `^8.3`, which allowed 8.5 all along — so a project could run on it while nothing ever
  tested that combination. Nothing changes for an existing install; this closes the gap
  between what the constraint permits and what is actually verified.

## [1.0.0] — 2026-07-23

The stable release. The API frozen at 0.9.0 has held through the whole 0.10.x line, and
this tag makes that guarantee binding under semver: breaking changes now wait for 2.0.

**What semver covers:** the module DSL you write against — the fluent builders on
`Attribute` and `Section`, and the `Module`/`Resource` classes you extend (their
properties and overridable methods). Plus three things a project depends on without
calling any PHP: the consent banner's markup, class names and `window.consent`; the path
`resources/js/consent.js`, which the frontend template bundles straight out of the
package; and the published view names under `leap::`. Methods marked `@internal` are the
package's own rendering and plumbing that happen to be `public` — not supported API, and
free to change in a minor. See [docs/upgrading.md](docs/upgrading.md).

Nothing in this release changes behaviour for an existing project. Upgrading from
0.10.18 is a constraint bump; `^0.10` never resolves to 1.0.0 on its own.

### Security

- **A password set in the admin is now hashed by the panel itself.** The editor wrote the
  typed value to the model and relied on the application's user model to cast it —
  `'password' => 'hashed'`, which a stock Laravel model has and a hand-written one may
  not. Without that cast the password was stored exactly as typed, and since Fortify
  verifies with `Hash::check()` the account could then never log in. The editor now
  hashes password attributes unless the model already casts them, so the stock case is
  byte-for-byte unchanged and the unsafe one is fixed.

### Changed

- **`leap.login_image` defaults to `null`** instead of a random `https://picsum.photos`
  photo, so a login page no longer calls a third party out of the box. The picsum URL
  stays in the config comment as a ready example. Only a freshly published
  `config/leap.php` is affected — an existing config keeps whatever it has.

### Fixed

- **The Users module follows the configured auth provider.** It derived its model from
  its own class name (`App\Models\User`), so a project authenticating anything else —
  `App\Models\Admin`, or a `User` outside `App\Models` — got a module pointed at a class
  that does not exist. It now reads the model from the auth provider.

- **`owenvoke/blade-fontawesome` must be 3.2.2 or newer.** The constraint was `^3.1`, and
  v3.2.1 of that package declares `"php": "^8.3"` while still using PHP 8.4-only syntax in
  `SyncIconsCommand`. On PHP 8.3 that file is a `ParseError`, and because its service
  provider registers the command, *every* artisan call fatals — not only the icon sync.
  v3.1.0 was safe (it correctly required php ^8.4, so Composer skipped it on 8.3); v3.2.1
  is the version that claims support it does not have.

- **`laravel/pint` must be 1.18.1 or newer.** Pint ships a prebuilt phar and the v1.18.0
  build is broken on its own: every run dies with `Call to undefined method
  PhpCsFixer\Config::setParallelConfig()`. Only a `--prefer-lowest` install picked it up.

- **Stale caching documentation.** `docs/upgrading.md` claimed `leap.cache` defaults to
  on and can be disabled with `LEAP_CACHE=false`, contradicting the same document a few
  lines down: the page-tree cache was removed and `LEAP_CACHE` is a no-op. `docs/template.md`
  still explained the consent design in terms of server-side page caching.
  `docs/caching.md` was already correct and is unchanged.

### Added

- **Releases are published from the changelog.** Pushing a tag now creates a GitHub
  release with that version's `CHANGELOG.md` section as its notes. Packagist reads tags
  and never needed them, which is why the first 78 tags produced none.

### Tests

360 tests, up from 291. New coverage for the `LeapAuth` middleware, the logout route, the
passkeys asset route (both enabled and disabled), the Dashboard/Navigation/Toasts
components, user and role management (including the two password bugs above), the
`NavigationItem` trait, `HasMedia`, the `Mediable` pivot, `ToastsValidationErrors` and
`ImageGenerator`. `tests/TestCase.php` now registers the `Leap` facade alias and the
blade-icons providers that package discovery gives a real application, so a full page
render works in a test.

## [0.10.18] — 2026-07-23

### Changed

- **The login, forgot-password, reset-password and 2FA screens share one scaffold.** The
  identical dialog/logo/status wrapper is now an `x-leap::auth-card` component. The status
  message it shows (`.form-message`) had no styling at all and now has some.

- **CSS tokens replace repeated literals.** `--leap-tint-dark` and `--leap-line-light` stand in
  for the two most-repeated `rgb(…)` values, `--leap-radius-lg` for the recurring 6px radius, and
  a hard-coded `#0cb` became `var(--leap-blue-light)`. Two opt-in utilities, `.leap-center` and
  `.leap-overlay`, are available for the repeated flex-center and full-screen-backdrop patterns.

- **The AI image pipeline lives in one trait.** The generate→park→preview→accept→store→describe
  flow existed twice, nearly line for line, in the editor and the filemanager. Both now use
  `InteractsWithAiImages`; the components only declare their permission, target folder and
  language file, and keep their own handling of the accepted image.

- **Repeated snippets became shared helpers.** `Leap::localize()` resolves a per-locale array to
  one string (was open-coded six times), `toastValidationErrors()` turns a failed validator into
  toasts (was copied three times), `Media::TYPES` is the single list of which extensions and MIME
  types count as image/bitmap/audio/video (was two drifting copies), `ImageGenerator::ratio()` is
  the one aspect-ratio parser, and `Attribute::foreign()`/`pivot()` share one relation builder.

- **`Resource::translatable()` resolves itself.** `hasTranslation()` used to return wrong results
  until something happened to call `getModel()` first (the editor called it just to prime the
  property). The translatable attribute list is now resolved lazily on first use.

- **Saving no longer asks the database three times whether pivots changed**, and the
  "N columns updated" toast now counts pivot changes too.

### Security

- **A permission only grants when it is strictly `true`.** An operator-precedence slip
  (`?? false === true` parses as `?? (false === true)`) meant the permission gate returned the
  raw stored value instead of comparing it to `true`, so any truthy value — `1`, `"yes"` — passed.
  The comparison is now explicit and covered by a regression test.

- **Uploaded SVGs are sanitized.** SVG is XML that can carry scripts, and the filemanager disk is
  typically public and same-origin — an SVG with an embedded `<script>` would run in the session
  of anyone opening its URL. Uploads now strip script and foreignObject blocks, inline event
  handlers and `javascript:` URLs before storing. Opt out with
  `leap.filemanager.sanitize_svg => false`.

- **Destructive filemanager operations re-validate their paths.** `$selectedFiles` and
  `$openFolders` are public Livewire properties a hostile client can set directly; delete, crop,
  focus-point, alt-text and upload now refuse dot-segments and absolute paths the same way rename
  already did.

- **The JSON read-only field escapes its values.** The `json` attribute renders stored JSON —
  often user-submitted data such as form submissions — and printed keys and values unescaped,
  allowing stored XSS in the admin. Same for the 2FA status message, escaped for consistency.

- **Disabling e-mail 2FA always checks permission.** `disableTwoFactorEmail()` took a
  `$silent` flag that skipped the permission check, and Livewire methods are client-callable with
  arbitrary arguments. The silent variant is now a protected method the client cannot reach.

### Fixed

- **Logging with a string context no longer crashes when another module is active.** The
  module-mismatch branch in `CanLog::log()` wrote an array key onto the string before the
  string-to-array normalisation ran — a PHP 8 fatal. The context is now normalised first.

- **Upload size config accepts lowercase suffixes.** `bytes()` only knew `K/M/G`, so a
  `upload_max_filesize` of `'20m'` was read as 20 bytes and rejected nearly every upload.

- **A module priority of `0` is honoured.** Module discovery and `getPriority()` used `?:`,
  which treats `0` as unset and overwrote it with a fallback; both now use `??`. Module discovery
  also no longer trips over an empty module list.

- **AI replies wrapped in prose decode reliably.** The JSON object in a provider reply was
  extracted with a greedy brace match, which corrupts the decode as soon as the surrounding prose
  contains a `}`. Replies are now decoded directly (code fences stripped) with a balanced-brace
  fallback, shared by translation and alt-text generation as `AiTask::decodeReply()`.

- **The disable-2FA and delete-passkey buttons are red again.** Profile used a `danger` button
  variant that has no CSS; they now use the same `secondary` variant as every other destructive
  button.

- **Debug leftovers removed.** A stray red `OPTION` style in the select styling, a red
  placeholder background on the file-browser dialog, a dead `padding` declaration and a
  commented-out `dd()`.

- **A slug only has to be unique among its siblings.** `HasSlug` has always scoped slug
  generation to siblings (the same slug may repeat under a different parent — `/a/options` and
  `/b/options` are different addresses), but the editor's `unique()` rule was global and rejected
  exactly that: a second "Options" page could not be created under another parent. The rule now
  carries the same sibling scope the model uses, taken from the parent being edited, so it
  re-scopes when you move the page. Flat models without a parent column keep global uniqueness.

- **Only a root page can claim the reserved "/" slug.** Sibling-scoped uniqueness (above) would
  otherwise allow one "/" per parent, and such a page is broken: "/" deeper in the tree resolves
  to its parent's own path, so the page is unreachable, shows up twice in the sitemap and can
  displace the real homepage. Giving "/" to a page that has a parent is now a validation error
  that says so, instead of silently producing a duplicate URL.

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
