# Backlog

Larger refactors deliberately left out of 0.10.18 (security/bugfix/dedupe release)
because each is big or risky enough to deserve its own PR and review. Recorded here so
they are not forgotten. Line references are omitted on purpose — they drift; the file
and method names are the anchor.

## Editor.php — split the god class
`src/Livewire/Editor.php` is ~1600 lines and mixes several concerns. Pull the
slug-follow, media-sync, section and translation logic into dedicated collaborators or
traits. The methods over 60 lines each — `openEditor()`, `checkSectionValues()`,
`rules()`, `save()` — are the seams to cut along.

**Why deferred:** touches the most-used surface in the panel; wants isolated tests per
extracted unit before it is safe.

## Resource::rows() — split
`Resource::rows()` is ~120 lines doing treeview scoping, eager loading, filtering,
locale-aware ordering, search and foreign/pivot/JSON post-processing in one deeply nested
body. Break into `applyFilters()`, `applyOrder()`, `hydrateForeign()` and friends.

**Why deferred:** it is the index query for every resource; a regression is wide-reaching.

## LeapContext — retire the global Context mirror
`LeapContext::setPermissions/setRoleName/setModule` still mirror into Laravel's global
`Context` (`Context::addHidden(...)`). That leaks the request-scoped state into queued
jobs — the exact thing the scoped binding was introduced to avoid — and the class docblock
already flags it as a 1.x backward-compat shim. Gate it behind a config flag, then remove
towards 2.0.

**Why deferred:** removing it is a behaviour change for anyone still reading the global
keys; belongs with the 2.0 cleanup.

## files.blade.php ↔ media.blade.php — merge
`resources/views/components/files.blade.php` and `media.blade.php` are near-identical
(a `<ul class="leap-files">` with per-item open-link + delete button; media adds tags and
an AI button). Share a base with a slot for the item body.

## Inline JS out of the blades
Large blocks of application JS live inline in blade `x-data`/`<script>` rather than in
`resources/js`: the filemanager crop/focus/alt-text logic, the ai-image Alpine component,
the lazy-TinyMCE controller and `setColumnWidths`. Move them to `resources/js/*.js` served
through the existing `AssetController::js()` mechanism (currently passkeys-only), so they
become testable and the templates shrink.
