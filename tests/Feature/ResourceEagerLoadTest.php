<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Tests\Fixtures\FilterAuthor;
use NickDeKruijk\Leap\Tests\Fixtures\FilterModel;
use NickDeKruijk\Leap\Tests\Fixtures\FilterResource;
use NickDeKruijk\Leap\Tests\Fixtures\FilterTag;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * An index that lists a pivot attribute renders it by reading the relation off every
 * row. Left to the resource's own $with that is one query per row, and on a project
 * running Model::preventLazyLoading() -- which the frontend template's own
 * AppServiceProvider turns on locally -- not a slow page but an exception:
 * "Attempted to lazy load [tags] on model [...] but lazy loading is disabled."
 *
 * Which relations the index reads is something the resource already knows, so it
 * eager loads them itself rather than asking every resource to repeat it.
 */
class ResourceEagerLoadTest extends TestCase
{
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

        Schema::create('filter_taggables', function (Blueprint $table): void {
            $table->unsignedBigInteger('filter_tag_id');
            $table->unsignedBigInteger('filter_taggable_id');
            $table->string('filter_taggable_type');
        });
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    /**
     * @return array<int, FilterTag>
     */
    private function seedTagged(int $rows): array
    {
        $update = FilterTag::create(['name' => 'Update']);
        $announcement = FilterTag::create(['name' => 'Announcement']);
        $author = FilterAuthor::create(['name' => 'Ada']);

        for ($i = 1; $i <= $rows; $i++) {
            $model = FilterModel::create(['title' => 'Row '.$i, 'author_id' => $author->id]);
            $model->tags()->attach($i % 2 ? $update->id : $announcement->id);
        }

        return [$update, $announcement];
    }

    /**
     * The reported failure, as the project meets it.
     */
    public function test_an_index_listing_a_pivot_survives_prevented_lazy_loading(): void
    {
        $this->seedTagged(3);

        Model::preventLazyLoading();

        $rows = (new FilterResource)->rows(index: true);

        $this->assertSame(['Update', 'Announcement', 'Update'], $rows->pluck('tags')->values()->toArray());
    }

    /**
     * The same thing counted rather than caught: the tags cost one query however many
     * rows there are, instead of one each.
     */
    public function test_the_pivot_costs_one_query_no_matter_how_many_rows(): void
    {
        $this->seedTagged(10);

        DB::enableQueryLog();
        (new FilterResource)->rows(index: true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $tagQueries = collect($queries)->filter(fn (array $query) => str_contains($query['query'], 'filter_taggables'))->count();

        $this->assertSame(1, $tagQueries, 'Ten rows must still take a single query for their tags.');
    }

    /**
     * The edit form reads the same relation off the record it opens, so it may not
     * regress into a lazy load either.
     */
    public function test_the_full_row_set_is_eager_loaded_too(): void
    {
        $this->seedTagged(2);

        Model::preventLazyLoading();

        $this->assertCount(2, (new FilterResource)->rows());
    }

    /**
     * A resource naming the same relation in $with keeps its own version of it --
     * the constraint it put there is the point of writing it.
     */
    public function test_a_resource_that_constrains_the_relation_itself_still_wins(): void
    {
        [$update] = $this->seedTagged(4);

        $resource = new FilterResource;
        $resource->with = ['tags' => fn ($query) => $query->whereKey($update->id)];

        Model::preventLazyLoading();

        $this->assertSame(
            ['Update', '', 'Update', ''],
            $resource->rows(index: true)->pluck('tags')->values()->toArray(),
        );
    }
}
