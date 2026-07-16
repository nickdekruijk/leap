<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsModel;
use NickDeKruijk\Leap\Tests\TestCase;

class HasSectionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sections_models', function (Blueprint $table): void {
            $table->id();
            $table->json('sections')->nullable();
            $table->timestamps();
        });
    }

    /**
     * The editor writes a translation set for every locale whatever the site's config,
     * and the seeders do the same.
     */
    private function model(array $sections): SectionsModel
    {
        return SectionsModel::create(['sections' => $sections]);
    }

    public function test_it_resolves_a_per_locale_field_to_the_current_locale(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        app()->setLocale('en');

        $model = $this->model([
            ['_name' => 'text', '_sort' => 1, 'body' => ['nl' => 'Hallo', 'en' => 'Hello']],
        ]);

        $this->assertSame('Hello', $model->sections()->first()['body']);
    }

    /**
     * The regression behind leap-template 0.10.4: a monolingual site (leap.locales null)
     * used to skip the collapsing entirely, so the raw ['nl' => …, 'en' => …] array
     * reached the view and blew up on htmlspecialchars().
     */
    public function test_it_resolves_a_per_locale_field_on_a_monolingual_site(): void
    {
        config(['leap.locales' => null]);
        app()->setLocale('nl');

        $model = $this->model([
            ['_name' => 'text', '_sort' => 1, 'body' => ['nl' => 'Hallo', 'en' => 'Hello']],
        ]);

        $this->assertSame('Hallo', $model->sections()->first()['body']);
    }

    public function test_it_falls_back_to_the_first_translation_when_the_locale_is_missing(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        app()->setLocale('en');

        $model = $this->model([
            ['_name' => 'text', '_sort' => 1, 'body' => ['nl' => 'Alleen Nederlands']],
        ]);

        $this->assertSame('Alleen Nederlands', $model->sections()->first()['body']);
    }

    /**
     * A list value is not a translation set. On a monolingual site the only thing telling
     * the two apart is that a translation set has string keys.
     */
    public function test_it_leaves_a_plain_list_alone(): void
    {
        config(['leap.locales' => null]);

        $model = $this->model([
            ['_name' => 'text', '_sort' => 1, 'items' => ['one', 'two']],
        ]);

        $this->assertSame(['one', 'two'], $model->sections()->first()['items']);
    }

    public function test_it_sorts_sections_by_their_sort_key(): void
    {
        $model = $this->model([
            ['_name' => 'b', '_sort' => 2],
            ['_name' => 'a', '_sort' => 1],
        ]);

        $this->assertSame(['a', 'b'], $model->sections()->pluck('_name')->all());
    }

    /**
     * _first and _last mark the edges of a run of same-named sections, so a template can
     * open and close a wrapper around the run.
     */
    public function test_it_marks_the_first_and_last_of_a_run_of_same_named_sections(): void
    {
        $model = $this->model([
            ['_name' => 'card', '_sort' => 1],
            ['_name' => 'card', '_sort' => 2],
            ['_name' => 'text', '_sort' => 3],
        ]);

        $sections = $model->sections()->values();

        $this->assertTrue($sections[0]['_first'], 'The run opens on the first card.');
        $this->assertFalse($sections[0]['_last'], 'A card follows, so the first does not close the run.');
        $this->assertFalse($sections[1]['_first']);
        $this->assertTrue($sections[1]['_last'], 'The run closes on the last card.');
        $this->assertTrue($sections[2]['_first']);
        $this->assertTrue($sections[2]['_last']);
    }

    public function test_it_returns_an_empty_collection_when_there_are_no_sections(): void
    {
        $this->assertTrue($this->model([])->sections()->isEmpty());
        $this->assertTrue(SectionsModel::create([])->sections()->isEmpty());
    }

    public function test_it_reads_a_differently_named_attribute(): void
    {
        Schema::table('sections_models', function (Blueprint $table): void {
            $table->json('blocks')->nullable();
        });

        $model = SectionsModel::create(['blocks' => [['_name' => 'text', '_sort' => 1]]]);

        $this->assertSame('text', $model->sections('blocks')->first()['_name']);
    }
}
