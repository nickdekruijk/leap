<?php

namespace Tests\Feature;

use App\Models\Page;
use Tests\TestCase;

class SeoTest extends TestCase
{
    public function test_document_title_appends_the_site_name_to_a_plain_page_title(): void
    {
        config(['app.name' => 'Acme']);
        $page = new Page;
        $page->title = 'Over ons';

        $this->assertSame('Over ons — Acme', $page->documentTitle());
    }

    public function test_document_title_uses_a_custom_html_title_verbatim(): void
    {
        config(['app.name' => 'Acme']);
        $page = new Page;
        $page->title = 'Over ons';
        $page->html_title = 'Custom SEO title';

        // A custom html_title stands on its own — the site name is not appended.
        $this->assertSame('Custom SEO title', $page->documentTitle());
    }

    public function test_document_title_is_the_site_name_when_there_is_no_page_title(): void
    {
        config(['app.name' => 'Acme']);

        $this->assertSame('Acme', (new Page)->documentTitle());
    }

    public function test_document_title_does_not_borrow_another_locales_html_title(): void
    {
        config(['app.name' => 'Acme', 'app.fallback_locale' => 'en']);
        app()->setLocale('nl');

        $page = new Page;
        $page->setTranslations('title', ['nl' => 'Home', 'en' => 'Home']);
        $page->setTranslations('html_title', ['en' => 'EN only title']); // nl is empty

        // The empty nl html_title must fall through to the page title, not the en one.
        $this->assertSame('Home — Acme', $page->documentTitle());
    }
}
