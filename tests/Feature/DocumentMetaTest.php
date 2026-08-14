<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Mediable;
use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\Fixtures\MediaModel;
use NickDeKruijk\Leap\Tests\Fixtures\PageLikeModel;
use NickDeKruijk\Leap\Tests\TestCase;
use NickDeKruijk\Leap\Traits\HasDocumentMeta;

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

    /**
     * A model with an image attached, the way a project's own page model is put
     * together: the media trait plus the meta trait.
     */
    private function pageWithImage(string $file): MediaModel
    {
        Schema::create('media_models', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
        });
        Storage::fake('public');

        $model = new class extends MediaModel
        {
            use HasDocumentMeta;

            protected $table = 'media_models';
        };
        $model->title = 'Post';
        $model->save();

        $gd = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($gd);
        Storage::disk('public')->put($file, ob_get_clean());

        Mediable::create([
            'media_id' => Media::forFile($file)->id,
            'mediable_type' => $model->getMorphClass(),
            'mediable_id' => $model->id,
            'mediable_attribute' => 'images',
            'sort' => 0,
        ]);

        return $model->fresh();
    }

    /**
     * The tag a scraper reads on its own, so it has to be absolute, and it has
     * to survive being parsed: Facebook, LinkedIn and the Schema Markup
     * Validator all choke on the raw space this used to write.
     */
    public function test_the_og_image_url_is_absolute_and_encoded(): void
    {
        $url = $this->pageWithImage('articles/01-Vlaamse Westhoek, 33.png')->ogImageUrl();

        $this->assertStringStartsWith('http', $url);
        $this->assertStringContainsString('01-Vlaamse%20Westhoek%2C%2033.png', $url);

        $parsed = parse_url($url);
        $this->assertArrayNotHasKey('query', $parsed);
        $this->assertSame('articles/01-Vlaamse Westhoek, 33.png', ltrim(rawurldecode(substr($parsed['path'], strrpos($parsed['path'], '/articles/'))), '/'));
    }

    public function test_the_og_image_url_of_an_ordinary_name_is_unchanged(): void
    {
        $this->assertSame(
            url('storage/header.png'),
            $this->pageWithImage('header.png')->ogImageUrl()
        );
    }
}
