<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\Fixtures\PageLikeModel;
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

    public function test_meta_description_uses_the_description_when_there_is_one(): void
    {
        $article = new Article;
        $article->description = 'The deliberate SEO text';
        $article->intro = 'The card text';

        $this->assertSame('The deliberate SEO text', $article->metaDescription());
    }

    public function test_meta_description_falls_back_to_the_intro(): void
    {
        $article = new Article;
        $article->intro = 'The card text';

        $this->assertSame('The card text', $article->metaDescription());
    }

    public function test_meta_description_is_empty_without_a_description_or_intro(): void
    {
        $this->assertSame('', (new Article)->metaDescription());
    }

    public function test_meta_description_does_not_borrow_another_locales_description(): void
    {
        config(['app.fallback_locale' => 'en']);
        $this->app->setLocale('nl');

        $article = new Article;
        $article->setTranslations('description', ['en' => 'EN only description']); // nl empty
        $article->setTranslations('intro', ['nl' => 'NL intro', 'en' => 'EN intro']);

        // The empty nl description must fall through to the nl intro, not the en
        // description — a borrowed locale would put English in a Dutch <head>.
        $this->assertSame('NL intro', $article->metaDescription());
    }

    /**
     * The reach for an intro must survive a model that has none: getTranslation()
     * throws AttributeIsNotTranslatable for an attribute outside $translatable, which
     * would take down every page render.
     */
    public function test_meta_description_works_on_a_model_without_an_intro(): void
    {
        $page = new PageLikeModel;
        $page->description = 'Page description';

        $this->assertSame('Page description', $page->metaDescription());
        $this->assertSame('', (new PageLikeModel)->metaDescription());
    }
}
