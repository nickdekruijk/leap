<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

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
        $attributes = ['id', 'title', 'slug', 'parent', 'menuitem'];
        foreach (Page::active()->get($attributes) as $page) {
            $pages[$page->parent ?: 0][] = $page->only($attributes);
            // dd($page->toArray(), $page, $page->slug, $page->only('slug'));
            // $pages['parents'][$page->id] = (int)$page->parent ?: 0;
            // $pages['titles'][$page->id] = $page->slug;
            // $pages['slugs'][$page->id] = $page->slug;
            // if ($page->menuitem) {
            //     $pages['menuitem'][$page->id] = true;
            // }
        }

        // Traverse the pages to find the active page and build menu
        function traverse(array &$pages, array $segments = [], $parent = 0, $depth = 0, $activeParent = true, $path = '')
        {
            foreach ($pages[$parent] as $n => $page) {
                // If page is a menuitems add it to do menu array too
                if ($page['menuitem']) {
                    $pages['menu'][$parent][$page['id']] = $page;
                    $pages['menu'][$parent][$page['id']]['url'] = $path . '/' . $page['slug'];
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
    public static function getMenu(int $parent = 0): null|array
    {
        return self::getPages()['menu'][$parent] ?? null;
    }

    public function route(string $uri = null): View
    {
        $pages = $this->getPages(explode('/', $uri ?: ''));

        abort_if(!$pages['current'], 404);

        // dd($this->getPages(), $this->getPages()['menu']);
        $page = Page::find($pages['current']['id']);

        return view('page', compact('page'));
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Context
    }

    /**
     * Display the specified resource.
     */
    public function show(Page $page)
    {
        //
    }
}
