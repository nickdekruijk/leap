<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\TestCase;

class HasLocaleRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->text('html_title')->nullable();
            $table->timestamps();
        });

        // Article::localeRouteName() is "article", so name the routes article.<locale>.
        Route::leapLocalized(['nl' => 'diensten', 'en' => 'services'], function (string $locale, string $segment): void {
            Route::get($segment.'/{slug}', fn (string $slug): string => $slug)->name('article.'.$locale);
        });

        // The name is set fluently after the route is added, so the collection's
        // name lookup can be stale until a request refreshes it. These tests call
        // route() directly (via localeUrls()) without a request first, so refresh
        // it now. A real app resolves localeUrls() during a request, where the
        // router has already refreshed the lookup.
        Route::getRoutes()->refreshNameLookups();
    }

    private function makeArticle(array $slugs): Article
    {
        $article = new Article(['active' => true]);
        foreach ($slugs as $locale => $slug) {
            $article->setTranslation('slug', $locale, $slug);
        }

        return $article;
    }

    public function test_locale_urls_carry_the_prefix_once_per_locale(): void
    {
        $this->app->setLocale('nl');
        $urls = $this->makeArticle(['nl' => 'foo', 'en' => 'bar'])->localeUrls();

        // The named route already includes the /en prefix; it must not be doubled.
        $this->assertSame('/diensten/foo', $urls['nl']['url']);
        $this->assertSame('/en/services/bar', $urls['en']['url']);
        $this->assertTrue($urls['nl']['active']);
        $this->assertFalse($urls['en']['active']);
    }

    public function test_locale_urls_omit_a_locale_without_a_slug_translation(): void
    {
        $urls = $this->makeArticle(['nl' => 'foo'])->localeUrls();

        $this->assertArrayHasKey('nl', $urls);
        $this->assertArrayNotHasKey('en', $urls);
    }

    public function test_locale_url_returns_the_active_locale_url(): void
    {
        $this->app->setLocale('en');
        $article = $this->makeArticle(['nl' => 'foo', 'en' => 'bar']);

        $this->assertSame('/en/services/bar', $article->localeUrl());
        $this->assertSame('/diensten/foo', $article->localeUrl('nl'));
        $this->assertNull($this->makeArticle(['nl' => 'foo'])->localeUrl('en'));
    }

    public function test_sitemap_entries_produce_one_url_per_routable_locale_with_alternates(): void
    {
        $article = $this->makeArticle(['nl' => 'foo', 'en' => 'bar']);
        $article->setTranslation('title', 'nl', 'Foo');
        $article->save();

        $entries = Article::sitemapEntries();

        $this->assertCount(2, $entries);

        $locs = $entries->pluck('loc')->all();
        $this->assertContains(url('/diensten/foo'), $locs);
        $this->assertContains(url('/en/services/bar'), $locs);

        // Each entry lists both locales as hreflang alternates (self included).
        foreach ($entries as $entry) {
            $this->assertSame([
                'nl' => url('/diensten/foo'),
                'en' => url('/en/services/bar'),
            ], $entry['alternates']);
        }
    }

    public function test_sitemap_entries_only_include_active_records(): void
    {
        $this->makeArticle(['nl' => 'foo', 'en' => 'bar'])->save();

        $inactive = $this->makeArticle(['nl' => 'baz', 'en' => 'qux']);
        $inactive->active = false;
        $inactive->save();

        $locs = Article::sitemapEntries()->pluck('loc')->all();

        $this->assertContains(url('/diensten/foo'), $locs);
        $this->assertNotContains(url('/diensten/baz'), $locs);
    }
}
