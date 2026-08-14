<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Tests\Fixtures\ReadingTimeModel;
use NickDeKruijk\Leap\Tests\TestCase;

class ReadingTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('reading_time_models', function (Blueprint $table): void {
            $table->id();
            $table->text('title')->nullable();
            $table->text('intro')->nullable();
            $table->json('sections')->nullable();
            $table->json('blocks')->nullable();
            // The override column a project may add. leap ships no migration for it, so
            // the trait has to work with and without it, see the last test.
            $table->integer('reading_time')->nullable();
            $table->timestamps();
        });

        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        app()->setLocale('nl');
    }

    /**
     * A run of $count distinct-looking words, so a miscount shows up as a wrong minute
     * rather than as a lucky round number.
     */
    private function words(int $count): string
    {
        return trim(str_repeat('woord ', $count));
    }

    public function test_it_counts_the_intro_and_every_section_of_an_article(): void
    {
        $model = ReadingTimeModel::create([
            'intro' => ['nl' => $this->words(100)],
            'sections' => [
                ['_name' => 'text', '_sort' => 1, 'head' => ['nl' => $this->words(10)], 'body' => ['nl' => $this->words(340)]],
                ['_name' => 'text', '_sort' => 2, 'body' => ['nl' => $this->words(450)]],
            ],
        ]);

        $this->assertSame(900, $model->wordCount());
        $this->assertSame(4, $model->readingTime());
    }

    /**
     * The column wins when a project fills it in: the count is a useful default, not a
     * fact about someone else's reading speed.
     */
    public function test_the_reading_time_column_overrides_the_count(): void
    {
        $model = ReadingTimeModel::create([
            'reading_time' => 42,
            'sections' => [['_name' => 'text', '_sort' => 1, 'body' => ['nl' => $this->words(900)]]],
        ]);

        $this->assertSame(42, $model->readingTime());
        // The count itself is untouched by the override.
        $this->assertSame(900, $model->wordCount());
    }

    /**
     * null, not 0: a view can then leave the whole line out instead of promising "0 min".
     */
    public function test_it_returns_null_when_there_is_nothing_to_read(): void
    {
        $model = ReadingTimeModel::create(['sections' => []]);

        $this->assertSame(0, $model->wordCount());
        $this->assertNull($model->readingTime());
    }

    /**
     * Without the double strip_tags around html_entity_decode, "&nbsp;" counts as a word
     * and "caf&eacute;" as two.
     */
    public function test_entities_and_markup_are_not_words(): void
    {
        $model = ReadingTimeModel::create([
            'sections' => [['_name' => 'text', '_sort' => 1, 'body' => ['nl' => '<p class="lead">caf&eacute;&nbsp;ok</p>']]],
        ]);

        $this->assertSame(2, $model->wordCount());
    }

    /**
     * The reason for counting rather than storing: the same article is not the same length
     * in both languages, so one integer per row is wrong for one of them.
     */
    public function test_it_counts_per_locale(): void
    {
        $model = ReadingTimeModel::create([
            'sections' => [[
                '_name' => 'text',
                '_sort' => 1,
                'body' => ['nl' => $this->words(200), 'en' => $this->words(900)],
            ]],
        ]);

        $this->assertSame(4, $model->readingTime('en'));
        $this->assertSame(1, $model->readingTime('nl'));

        // Without a locale it follows the active one, like the rest of the frontend.
        app()->setLocale('en');
        $this->assertSame(4, $model->readingTime());
    }

    /**
     * A section switched off in the editor is not on the page and so is not read.
     */
    public function test_an_inactive_section_is_not_counted(): void
    {
        $model = ReadingTimeModel::create([
            'sections' => [
                ['_name' => 'text', '_sort' => 1, 'active' => false, 'body' => ['nl' => $this->words(900)]],
                ['_name' => 'text', '_sort' => 2, 'active' => true, 'body' => ['nl' => $this->words(225)]],
            ],
        ]);

        $this->assertSame(225, $model->wordCount());
        $this->assertSame(1, $model->readingTime());
    }

    /**
     * HasSections falls back to the first translation, so a Dutch page really does show the
     * English text when that is all there is. The reading time has to describe what is on
     * the screen, not what is stored under one key.
     */
    public function test_it_counts_the_text_the_page_falls_back_to(): void
    {
        $model = ReadingTimeModel::create([
            'sections' => [['_name' => 'text', '_sort' => 1, 'body' => ['en' => $this->words(900)]]],
        ]);

        $this->assertSame(900, $model->wordCount('nl'));
        $this->assertSame(4, $model->readingTime('nl'));
    }

    /**
     * On a monolingual site (leap.locales null) the seeders still write every locale into
     * the column. HasSections collapses that to one language, and so does the count:
     * adding them up would double the reading time of every page.
     */
    public function test_it_counts_one_language_on_a_monolingual_site(): void
    {
        config(['leap.locales' => null]);
        app()->setLocale('nl');

        $model = ReadingTimeModel::create([
            'sections' => [[
                '_name' => 'text',
                '_sort' => 1,
                'body' => ['nl' => $this->words(200), 'en' => $this->words(900)],
            ]],
        ]);

        $this->assertSame(200, $model->wordCount());
        $this->assertSame(1, $model->readingTime());
    }

    /**
     * getTranslation() throws AttributeIsNotTranslatable on an attribute outside
     * $translatable, so a model with a plain intro column would take the page down over one
     * field rather than count a few words fewer.
     */
    public function test_it_reads_an_intro_the_model_does_not_translate(): void
    {
        $untranslated = new class extends ReadingTimeModel
        {
            /** @var array<int, string> */
            public array $translatable = ['title'];
        };

        $model = $untranslated->newInstance([
            'intro' => $this->words(10),
            'sections' => [['_name' => 'text', '_sort' => 1, 'body' => ['nl' => $this->words(215)]]],
        ]);
        $model->save();

        $this->assertSame(225, $model->fresh()->wordCount());
        $this->assertSame(1, $model->fresh()->readingTime());
    }

    /**
     * Sections do not have to live in a column called "sections", the same as HasSections.
     */
    public function test_it_counts_a_differently_named_sections_column(): void
    {
        $model = ReadingTimeModel::create([
            'blocks' => [['_name' => 'text', '_sort' => 1, 'body' => ['nl' => $this->words(450)]]],
        ]);

        $this->assertSame(0, $model->wordCount());
        $this->assertSame(450, $model->wordCount(null, 'blocks'));
        $this->assertSame(2, $model->readingTime(null, 'blocks'));
    }

    /**
     * A page with one paragraph on it still takes a moment, so the result is never rounded
     * down to nothing.
     */
    public function test_a_short_page_still_takes_a_minute(): void
    {
        $model = ReadingTimeModel::create([
            'sections' => [['_name' => 'text', '_sort' => 1, 'body' => ['nl' => $this->words(10)]]],
        ]);

        $this->assertSame(1, $model->readingTime());
    }

    /**
     * A model without the override column at all: $this->reading_time is null in Eloquent,
     * so the trait counts instead of erroring.
     */
    public function test_it_works_on_a_model_without_a_reading_time_column(): void
    {
        Schema::table('reading_time_models', function (Blueprint $table): void {
            $table->dropColumn('reading_time');
        });

        $model = ReadingTimeModel::create([
            'sections' => [['_name' => 'text', '_sort' => 1, 'body' => ['nl' => $this->words(450)]]],
        ]);

        $this->assertSame(2, $model->readingTime());
    }
}
