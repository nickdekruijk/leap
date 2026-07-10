<?php

namespace Tests\Feature;

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
}
