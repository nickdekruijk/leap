<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultilingualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('leap.locales')) {
            $this->markTestSkipped('leap.locales is not configured; the site is monolingual.');
        }

        $this->seed(PageSeeder::class);
    }

    public function test_the_default_locale_is_served_unprefixed(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_a_secondary_locale_is_served_under_its_prefix(): void
    {
        $secondary = array_keys(config('leap.locales'))[1] ?? null;
        $this->assertNotNull($secondary, 'Expected at least two configured locales');

        $this->get('/'.$secondary)->assertOk();
    }

    public function test_the_homepage_lists_hreflang_alternates(): void
    {
        $this->get('/')->assertSee('hreflang', false);
    }

    public function test_the_sitemap_has_one_entry_per_page_per_locale(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

        $this->assertCount(Page::active()->count() * 2, $xml->url);
    }

    public function test_the_sitemap_entry_uses_the_locale_specific_slug(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('/over-ons/diensten'), $body);
        $this->assertStringContainsString(url('/en/about-us/services'), $body);
    }

    public function test_the_sitemap_lists_hreflang_alternates_including_itself(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $body);
        $this->assertStringContainsString('hreflang="nl" href="'.url('/over-ons/diensten').'"', $body);
        $this->assertStringContainsString('hreflang="en" href="'.url('/en/about-us/services').'"', $body);
    }

    public function test_the_sitemap_omits_a_locale_when_the_page_has_no_slug_translation_there(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        // withoutEvents bypasses HasSlug's saving hook, which would otherwise
        // regenerate an empty slug from the title — simulating a page that
        // genuinely has no translation for this locale (e.g. added before
        // the locale was configured).
        Page::withoutEvents(function (): void {
            $page = Page::find(4); // 'contact', a root page with no children
            $page->setTranslation('slug', 'en', '');
            $page->save();
        });

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString(url('/en/contact'), $body);
        $this->assertStringContainsString(url('/contact'), $body);
    }

    public function test_the_sitemap_omits_a_child_page_when_its_parent_has_no_slug_translation_there(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        Page::withoutEvents(function (): void {
            $parent = Page::find(2); // 'over-ons'/'about-us', parent of 'diensten'/'services'
            $parent->setTranslation('slug', 'en', '');
            $parent->save();
        });

        // Neither parent nor child should appear under /en, even though the
        // child ('diensten'/'services') still has its own en slug — proves
        // buildLocalePath() cascades non-routability from the ancestor.
        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString(url('/en/about-us'), $body);
        $this->assertStringNotContainsString(url('/en/about-us/services'), $body);
        $this->assertStringContainsString(url('/over-ons/diensten'), $body);
    }
}
