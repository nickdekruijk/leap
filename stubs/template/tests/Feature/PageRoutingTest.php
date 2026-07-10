<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    public function test_homepage_resolves_at_the_root(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_homepage_is_not_also_reachable_at_home(): void
    {
        // The homepage is the page whose slug is "/", not one with slug "home".
        $this->get('/home')->assertNotFound();
    }

    public function test_a_subpage_resolves(): void
    {
        // A root page (no parent) whose slug isn't the homepage, resolved per locale
        $page = Page::active()->get()->first(fn (Page $page): bool => ! $page->parent && $page->slug !== '/');
        $this->assertNotNull($page, 'Expected at least one non-home root page from the seeder');

        $this->get('/'.$page->slug)->assertOk();
    }

    public function test_an_unknown_slug_returns_404(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound();
    }

    public function test_the_sitemap_has_a_single_plain_entry_per_page_when_monolingual(): void
    {
        config(['leap.locales' => null]);

        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

        $this->assertCount(Page::active()->count(), $xml->url);
        $this->assertStringNotContainsString('xhtml:link', $this->get('/sitemap.xml')->getContent());
    }
}
