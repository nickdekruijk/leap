<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_slug_is_generated_from_the_title(): void
    {
        $page = Page::forceCreate(['title' => 'About Our Company', 'active' => true]);

        $this->assertSame('about-our-company', $page->fresh()->slug);
    }

    public function test_sibling_pages_with_the_same_title_get_a_unique_slug(): void
    {
        $first = Page::forceCreate(['title' => 'Services', 'active' => true]);
        $second = Page::forceCreate(['title' => 'Services', 'active' => true]);

        $this->assertSame('services', $first->fresh()->slug);
        $this->assertNotSame('services', $second->fresh()->slug);
        $this->assertStringStartsWith('services-', $second->fresh()->slug);
    }

    public function test_the_homepage_slug_is_never_slugified(): void
    {
        $page = Page::forceCreate(['title' => 'Home', 'slug' => '/', 'active' => true]);

        $this->assertSame('/', $page->fresh()->slug);
    }
}
