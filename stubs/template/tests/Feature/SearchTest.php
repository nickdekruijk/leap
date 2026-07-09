<?php

namespace Tests\Feature;

use App\Livewire\Search;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('leap.locales')) {
            $this->markTestSkipped('leap.locales is not configured; the site is monolingual.');
        }
    }

    /**
     * The two configured locales, default first.
     *
     * @return array{0: string, 1: string}
     */
    private function locales(): array
    {
        $locales = array_keys(config('leap.locales'));
        if (count($locales) < 2) {
            $this->markTestSkipped('Search locale scoping needs at least two locales.');
        }

        return [$locales[0], $locales[1]];
    }

    private function makePage(array $title, array $sections = [], ?array $slug = null): Page
    {
        [$default, $secondary] = $this->locales();
        $page = new Page;
        $page->setTranslations('title', $title);
        $page->setTranslations('slug', $slug ?? [$default => 'p'.uniqid(), $secondary => 'p'.uniqid()]);
        $page->setTranslations('description', [$default => '', $secondary => '']);
        $page->active = true;
        $page->sections = $sections;
        $page->sort = 1;
        $page->save();

        return $page;
    }

    /**
     * @return array<int, string> matched page titles in the active locale
     */
    private function search(string $term, string $locale): array
    {
        app()->setLocale($locale);

        return Livewire::test(Search::class)
            ->set('query', $term)
            ->instance()
            ->results()
            ->pluck('title')
            ->all();
    }

    public function test_title_is_matched_in_the_active_locale_only(): void
    {
        [$default, $secondary] = $this->locales();
        $this->makePage([$default => 'Over ons', $secondary => 'About us']);

        $this->assertContains('Over ons', $this->search('over', $default));
        // The secondary-locale title must not leak into the default locale
        $this->assertNotContains('Over ons', $this->search('about', $default));
        $this->assertContains('About us', $this->search('about', $secondary));
    }

    public function test_section_content_is_matched_in_the_active_locale_only(): void
    {
        [$default, $secondary] = $this->locales();
        $this->makePage(
            [$default => 'Diensten', $secondary => 'Services'],
            [['_name' => 'default', 'body' => [$default => '<p>Onze missie</p>', $secondary => '<p>Our mission</p>']]],
        );

        $this->assertContains('Diensten', $this->search('missie', $default));
        // The secondary-locale section body must not leak into the default locale
        $this->assertNotContains('Diensten', $this->search('mission', $default));
        $this->assertContains('Services', $this->search('mission', $secondary));
    }

    public function test_legacy_plain_string_rows_do_not_crash_the_query(): void
    {
        [$default] = $this->locales();

        // A pre-multilingual row: plain-string columns, not translations JSON.
        DB::table('pages')->insert([
            'title' => 'Legacy Titel',
            'description' => 'oude beschrijving',
            'slug' => 'legacy-page',
            'active' => true,
            'sections' => json_encode([['_name' => 'default', 'body' => '<p>Legacy sectie tekst</p>']]),
            'sort' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Must not throw (json_valid guard), and legacy section text stays
        // searchable. The plain-string title reads back empty via Spatie (until the
        // row is migrated), so assert the row is found rather than its title text.
        $this->assertCount(1, $this->search('legacy sectie', $default));
    }
}
