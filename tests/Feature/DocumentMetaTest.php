<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\TestCase;

class DocumentMetaTest extends TestCase
{
    public function test_document_title_appends_the_site_name_to_a_plain_title(): void
    {
        config(['app.name' => 'Acme']);
        $article = new Article;
        $article->title = 'About us';

        $this->assertSame('About us — Acme', $article->documentTitle());
    }

    public function test_document_title_uses_a_custom_html_title_verbatim(): void
    {
        config(['app.name' => 'Acme']);
        $article = new Article;
        $article->title = 'About us';
        $article->html_title = 'Custom SEO title';

        $this->assertSame('Custom SEO title', $article->documentTitle());
    }

    public function test_document_title_is_the_site_name_when_there_is_no_title(): void
    {
        config(['app.name' => 'Acme']);

        $this->assertSame('Acme', (new Article)->documentTitle());
    }

    public function test_document_title_does_not_borrow_another_locales_html_title(): void
    {
        config(['app.name' => 'Acme', 'app.fallback_locale' => 'en']);
        $this->app->setLocale('nl');

        $article = new Article;
        $article->setTranslations('title', ['nl' => 'Home', 'en' => 'Home']);
        $article->setTranslations('html_title', ['en' => 'EN only title']); // nl empty

        // The empty nl html_title must fall through to the page title, not the en one.
        $this->assertSame('Home — Acme', $article->documentTitle());
    }

    public function test_og_image_url_is_null_without_media_or_sections(): void
    {
        $this->assertNull((new Article)->ogImageUrl());
    }
}
