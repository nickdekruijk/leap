# Caching

The frontend template caches its page tree, because pages change rarely but
`PageController::getPages()` runs on every request (it builds the navigation and
resolves the current page).

## Configuration

```php
// config/leap.php
'cache' => env('LEAP_CACHE', true),
```

- **on** (default) — the segment-independent page data (the database query plus
  per-locale slug/title resolution) is cached with `Cache::rememberForever`. The cache
  key includes the active locale, because translated slugs/titles resolve at build
  time.
- **off** (`LEAP_CACHE=false`) — no persistent cache; a per-request `once()` memo still
  avoids duplicate work within a single request.

## Invalidation

The `Page` model flushes the cache for every configured locale on `saved`, `deleted`
and `restored`. Because admin edits go through Eloquent, changes appear immediately —
so leaving the cache on is safe in every environment, including local development.

For mutations that bypass Eloquent (raw queries, imports), clear it manually:

```bash
php artisan cache:clear
```
