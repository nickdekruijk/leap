# Caching

`PageController::getPages()` runs on every request (it builds the navigation and resolves
the current page), so it is **memoized per request** with `once()` — it runs once no
matter how many times the navigation, the current page and the language switcher ask for
it.

There is **no persistent cross-request cache**. Pages are few and the query is a small
indexed one, so caching it saved a negligible amount against a real risk of stale data
(an edit not showing up, a cache not cleared on deploy). Removing it keeps the template
correct with no `config('leap.cache')`, no model events and no `cache:clear` to remember.

If a project ever grows a genuinely large page tree and profiling shows `loadPages()` is
hot, wrap it in `Cache::rememberForever(...)` in your copy of `PageController` and flush
it from the `Page` model on `saved`/`deleted`/`restored`.
