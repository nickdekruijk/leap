<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Tests\Fixtures\FilterAuthor;
use NickDeKruijk\Leap\Tests\Fixtures\FilterModel;
use NickDeKruijk\Leap\Tests\Fixtures\FilterOtherModel;
use NickDeKruijk\Leap\Tests\Fixtures\FilterResource;
use NickDeKruijk\Leap\Tests\Fixtures\FilterTag;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A pivot column renders as the values of the row joined into one string, and the filter
 * used to be that string: a row tagged "Update, Announcement" became a filter option of
 * its own, and filtering by "Update" alone matched nothing. Both the options and the
 * filter itself are keyed by the id of the related record instead.
 */
class ResourceFilterTest extends TestCase
{
    private FilterTag $update;

    private FilterTag $announcement;

    private FilterTag $unused;

    private FilterAuthor $ada;

    private FilterAuthor $linus;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('filter_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('filter_authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('filter_models', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('author_id')->nullable();
        });

        Schema::create('filter_other_models', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });

        Schema::create('filter_taggables', function (Blueprint $table): void {
            $table->unsignedBigInteger('filter_tag_id');
            $table->unsignedBigInteger('filter_taggable_id');
            $table->string('filter_taggable_type');
        });

        $this->update = FilterTag::create(['name' => 'Update']);
        $this->announcement = FilterTag::create(['name' => 'Announcement']);
        $this->unused = FilterTag::create(['name' => 'Unused']);

        $this->ada = FilterAuthor::create(['name' => 'Ada']);
        $this->linus = FilterAuthor::create(['name' => 'Linus']);
    }

    private function resource(array $filters = []): FilterResource
    {
        $resource = new FilterResource;

        foreach ($filters as $attribute => $value) {
            $resource->filterBy($attribute, $value);
        }

        return $resource;
    }

    /**
     * The case that prompted this: one row carries both tags, so its rendered value is
     * "Update, Announcement" and never equalled the single tag being filtered by.
     */
    public function test_a_row_with_two_tags_is_found_by_one_of_them(): void
    {
        $both = FilterModel::create(['title' => 'Two tags']);
        $both->tags()->attach([$this->update->id, $this->announcement->id]);

        $one = FilterModel::create(['title' => 'One tag']);
        $one->tags()->attach($this->update->id);

        FilterModel::create(['title' => 'No tags']);

        $rows = $this->resource(['tags' => (string) $this->update->id])->rows(index: true);

        $this->assertEquals(['Two tags', 'One tag'], $rows->pluck('title')->values()->toArray());
    }

    public function test_the_pivot_filter_offers_single_tags_not_combinations(): void
    {
        $both = FilterModel::create(['title' => 'Two tags']);
        $both->tags()->attach([$this->update->id, $this->announcement->id]);

        $resource = $this->resource();
        $attribute = $resource->indexAttributes()->where('name', 'tags')->first();

        $this->assertSame([
            $this->update->id => 'Update',
            $this->announcement->id => 'Announcement',
        ], $resource->filterData($attribute));
    }

    public function test_a_tag_that_is_not_attached_is_not_offered(): void
    {
        $row = FilterModel::create(['title' => 'One tag']);
        $row->tags()->attach($this->update->id);

        $resource = $this->resource();
        $attribute = $resource->indexAttributes()->where('name', 'tags')->first();

        $this->assertArrayNotHasKey($this->unused->id, $resource->filterData($attribute));
    }

    /**
     * The pivot table is shared with FilterOtherModel, whose tags belong to another
     * resource: without the morph constraint they would show up here.
     */
    public function test_a_tag_of_another_morph_type_is_not_offered(): void
    {
        $row = FilterModel::create(['title' => 'One tag']);
        $row->tags()->attach($this->update->id);

        $other = FilterOtherModel::create(['title' => 'Other']);
        $other->tags()->attach($this->announcement->id);

        $resource = $this->resource();
        $attribute = $resource->indexAttributes()->where('name', 'tags')->first();

        $this->assertSame([$this->update->id => 'Update'], $resource->filterData($attribute));
    }

    public function test_the_foreign_filter_offers_used_ids_and_filters_by_them(): void
    {
        FilterModel::create(['title' => 'By Ada', 'author_id' => $this->ada->id]);
        FilterModel::create(['title' => 'By nobody']);

        $resource = $this->resource();
        $attribute = $resource->indexAttributes()->where('name', 'author_id')->first();

        // Linus wrote nothing, so filtering by him could only ever return an empty index
        $this->assertSame([$this->ada->id => 'Ada'], $resource->filterData($attribute));

        $rows = $this->resource(['author_id' => (string) $this->ada->id])->rows(index: true);

        $this->assertEquals(['By Ada'], $rows->pluck('title')->values()->toArray());
    }

    public function test_a_plain_column_still_filters_on_its_value(): void
    {
        FilterModel::create(['title' => 'Update']);
        FilterModel::create(['title' => 'Announcement']);

        $rows = $this->resource(['title' => 'Update'])->rows(index: true);

        $this->assertEquals(['Update'], $rows->pluck('title')->values()->toArray());
    }

    public function test_the_empty_option_filters_nothing(): void
    {
        $row = FilterModel::create(['title' => 'One tag']);
        $row->tags()->attach($this->update->id);
        FilterModel::create(['title' => 'No tags']);

        $rows = $this->resource(['tags' => 'NULL', 'title' => 'NULL'])->rows(index: true);

        $this->assertCount(2, $rows);
    }
}
