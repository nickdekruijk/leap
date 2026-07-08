<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PageController extends Controller
{
    /**
     * Load the active pages grouped by parent id (segment-independent).
     *
     * This is the expensive part of getPages() — the database query plus
     * per-locale slug/title resolution — so it is cached when config('leap.cache')
     * is on (the default). Pages change rarely and the cache is flushed by the
     * Page model on save/delete. The cache key includes the active locale because
     * translated slugs/titles are resolved at build time.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected static function loadPages(): array
    {
        $build = function () {
            $pages = [];

            // Get all pages but only the attributes we need for navigation
            $attributes = ['id', 'title', 'slug', 'parent', 'menuitem', 'sections'];
            foreach (Page::active()->get($attributes) as $page) {
                // Only keep section titles that are flagged as a menu item, to add them as in-page anchors later
                if (isset($page->sections)) {
                    $menuitemTitles = [];
                    foreach (collect($page->sections)->where('menuitem', 1)->sortBy('_sort') as $menuitem) {
                        $menuitemTitles[] = $menuitem['head'];
                    }
                    $page->sections = $menuitemTitles;
                }
                $pages[$page->parent ?: 0][] = $page->only($attributes);
            }

            return $pages;
        };

        if (config('leap.cache', true)) {
            return Cache::rememberForever('leap.pages.'.app()->getLocale(), $build);
        }

        return $build();
    }

    /**
     * Flush the cached page tree for every configured locale.
     *
     * Called by the Page model whenever a page is saved or deleted.
     */
    public static function flushPageCache(): void
    {
        $locales = array_keys(config('leap.locales') ?: [app()->getLocale() => null]);
        foreach ($locales as $locale) {
            Cache::forget('leap.pages.'.$locale);
        }
    }

    /**
     * Get all active pages, structured so we can build the navigation and resolve
     * the current page. Memoized for the duration of the request via once().
     *
     * @return array<string, mixed>
     */
    public static function getPages(array $segments = []): array
    {
        return once(function () use ($segments) {
            // Segment-independent page data (cached per locale)
            $pages = static::loadPages();
            $pages['menu'] = [];
            $pages['current'] = null;

            // URL prefix for the active locale (empty for the default/only locale)
            $prefix = static::localePrefix();

            // Traverse the pages to find the active page and build the menu
            $traverse = function (array &$pages, array $segments = [], int $parent = 0, int $depth = 0, bool $activeParent = true, string $path = '') use (&$traverse, $prefix) {
                foreach ($pages[$parent] ?? [] as $page) {
                    // If the page is a menu item, add it to the menu array
                    if ($page['menuitem']) {
                        $pages['menu'][$parent][$page['id']] = $page;
                        $pages['menu'][$parent][$page['id']]['url'] = $prefix.(rtrim($path.'/'.$page['slug'], '/') ?: '/');
                    }
                    // Add in-page anchor links for sections flagged as menu items
                    foreach ($page['sections'] ?: [] as $i => $section) {
                        $pages['menu'][$page['id']][$i] = ['title' => $section];
                        $pages['menu'][$page['id']][$i]['url'] = $prefix.$path.'/'.$page['slug'].'#'.Str::slug($section);
                    }

                    // The homepage is the page whose slug is "/", not simply the first page (order-independent).
                    // Pages without a slug in the active locale (empty) are not routable, so never match.
                    $active = $activeParent && isset($segments[$depth]) && $page['slug'] !== '' && ($segments[$depth] === $page['slug'] || ($segments[$depth] == '' && $page['slug'] == '/'));
                    if ($active) {
                        $pages['active'][$page['id']] = true;
                        // If the active page is the last segment, it is the current page
                        if ($depth == count($segments) - 1) {
                            $pages['current'] = $page;
                        }
                    }

                    // Traverse further if the page has children
                    if (isset($pages[$page['id']])) {
                        $traverse($pages, $segments, $page['id'], $depth + 1, $active, $path.'/'.$page['slug']);
                    }
                }
            };
            $traverse($pages, $segments);

            return $pages;
        });
    }

    /**
     * Get only the menu part of the getPages result.
     *
     * @return array<int|string, mixed>
     */
    public static function getMenu(int $parent = 0): array
    {
        return static::getPages()['menu'][$parent] ?? [];
    }

    /**
     * Frontend catch-all route.
     */
    public function route(?string $uri = null): View
    {
        $segments = explode('/', $uri ?: '');

        // When multilingual, strip and apply a leading locale prefix (gated on leap.locales)
        static::detectLocale($segments);

        // A bare locale prefix ("/en") leaves no segments; the homepage matches an empty one
        if (empty($segments)) {
            $segments = [''];
        }

        $pages = static::getPages($segments);

        abort_if(! $pages['current'], 404);

        $page = Page::find($pages['current']['id']);

        return view('page', compact('page'));
    }

    /**
     * XML sitemap of all active pages.
     */
    public function sitemap(): Response
    {
        $pages = Page::active()->get(['id', 'slug', 'parent', 'updated_at']);
        $map = $pages->keyBy('id');
        $prefix = static::localePrefix();

        $urls = $pages->map(fn (Page $page): array => [
            'loc' => url($prefix.static::buildPath($page, $map)),
            'lastmod' => $page->updated_at?->toAtomString(),
        ]);

        return response()
            ->view('sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Detect a leading locale segment and apply it. No-op unless leap.locales
     * defines more than the default locale. The default locale stays unprefixed.
     */
    protected static function detectLocale(array &$segments): void
    {
        $locales = config('leap.locales');
        if (! $locales) {
            return;
        }

        $default = array_key_first($locales);
        if (isset($segments[0]) && $segments[0] !== '' && $segments[0] !== $default && array_key_exists($segments[0], $locales)) {
            app()->setLocale(array_shift($segments));
        }
    }

    /**
     * URL prefix for the active locale: empty for the default/only locale, "/xx" otherwise.
     */
    protected static function localePrefix(): string
    {
        $locales = config('leap.locales');
        if (! $locales) {
            return '';
        }

        return app()->getLocale() === array_key_first($locales) ? '' : '/'.app()->getLocale();
    }

    /**
     * Build the full path for a page by walking its parent chain.
     *
     * @param  \Illuminate\Support\Collection<int, Page>  $map
     */
    /**
     * URLs for the given page in each configured locale, for a language switcher.
     * Empty unless multilingual.
     *
     * @return array<string, array{name: string, url: string, active: bool}>
     */
    public static function localeUrls(?Page $page): array
    {
        $locales = config('leap.locales');
        if (! $locales || ! $page) {
            return [];
        }

        $default = array_key_first($locales);
        $urls = [];
        foreach ($locales as $locale => $name) {
            $prefix = $locale === $default ? '' : '/'.$locale;
            $urls[$locale] = [
                'name' => $name,
                'url' => $prefix.static::localePath($page, $locale),
                'active' => $locale === app()->getLocale(),
            ];
        }

        return $urls;
    }

    /**
     * Build a page's path in a specific locale by walking the parent chain.
     */
    protected static function localePath(Page $page, string $locale): string
    {
        $slug = $page->getTranslation('slug', $locale, false) ?: '';
        $path = $page->parent && ($parent = Page::find($page->parent))
            ? static::localePath($parent, $locale).'/'.$slug
            : '/'.$slug;

        return rtrim($path, '/') ?: '/';
    }

    protected static function buildPath(Page $page, $map): string
    {
        $slug = $page->slug ?: '';

        if ($page->parent && isset($map[$page->parent])) {
            return rtrim(static::buildPath($map[$page->parent], $map).'/'.$slug, '/') ?: '/';
        }

        return rtrim('/'.$slug, '/') ?: '/';
    }
}
