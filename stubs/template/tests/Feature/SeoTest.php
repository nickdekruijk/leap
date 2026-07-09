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

    public function test_meta_title_prefers_html_title_over_title_without_a_suffix(): void
    {
        $withHtmlTitle = new Page;
        $withHtmlTitle->title = 'Over ons';
        $withHtmlTitle->html_title = 'Custom SEO title';
        $this->assertSame('Custom SEO title', $withHtmlTitle->metaTitle());

        $plain = new Page;
        $plain->title = 'Over ons';
        $this->assertSame('Over ons', $plain->metaTitle());
    }
}
