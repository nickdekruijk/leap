<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class PageController extends Controller
{
    /**
     * Get all active pages from the model and return them in a way we can use to build navigation and get current page
     *
     * @return array
     */
    public static function getPages(array $segments = []): array
    {
        // Prevent getting all pages over and over
        if (Context::getHidden('PageController.pages')) {
            return Context::getHidden('PageController.pages');
        }

        // Boilerplate for the getPages array
        $pages = [
            'menu' => [],
            'current' => null,
        ];

        // Get all pages but only the attributes we need for navigation
        $attributes = ['id', 'title', 'slug', 'parent', 'menuitem', 'sections'];
        foreach (Page::active()->get($attributes) as $page) {
            // Only save titles for sections with menuitem set so they can be added to the navigation menu later
            if (isset($page->sections)) {
                $menuitemTitles = [];
                foreach (collect($page->sections)->where('menuitem', 1)->sortBy('_sort') as $menuitem) {
                    $menuitemTitles[] = $menuitem['head'];
                }
                $page->sections = $menuitemTitles;
            }
            $pages[$page->parent ?: 0][] = $page->only($attributes);
        }

        // Traverse the pages to find the active page and build menu
        function traverse(array &$pages, array $segments = [], $parent = 0, $depth = 0, $activeParent = true, $path = '')
        {
            foreach ($pages[$parent] ?? [] as $n => $page) {
                // If page is a menuitems add it to do menu array too
                if ($page['menuitem']) {
                    $pages['menu'][$parent][$page['id']] = $page;
                    $pages['menu'][$parent][$page['id']]['url'] = rtrim($path . '/' . $page['slug'], '/') ?: '/';
                }
                foreach ($page['sections'] ?: [] as $i => $section) {
                    $pages['menu'][$page['id']][$i] = ['title' => $section];
                    $pages['menu'][$page['id']][$i]['url'] = $path . '/' . $page['slug'] . '#' . Str::slug($section);
                }

                // Determine active state for the page by checking if slug matches of if it's the first child when segment is empty
                $active = $activeParent && isset($segments[$depth]) && ($segments[$depth] === $page['slug'] || ($segments[$depth] == '' && $n == 0));
                if ($active) {
                    $pages['active'][$page['id']] = true;
                    // If the active page is the last segment, it is the current page
                    if ($depth == count($segments) - 1) {
                        $pages['current'] = $page;
                    }
                }

                // Traverse further if the page has children
                if (isset($pages[$page['id']])) {
                    traverse($pages, $segments, $page['id'], $depth + 1, $active, $path . '/' . $page['slug']);
                }
            }
        }
        traverse($pages, $segments);

        // Store in context
        Context::addHidden('PageController.pages', $pages);

        return $pages;
    }

    /**
     * Get only the menu part of getPages result
     *
     * @return array
     */
    public static function getMenu(int $parent = 0): array
    {
        return self::getPages()['menu'][$parent] ?? [];
    }

    public function route(null|string $uri = null): View
    {
        $pages = $this->getPages(explode('/', $uri ?: ''));

        abort_if(!$pages['current'], 404);

        $page = Page::find($pages['current']['id']);

        return view('page', compact('page'));
    }
}
