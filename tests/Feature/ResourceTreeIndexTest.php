<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Tests\Fixtures\TreeIndexModel;
use NickDeKruijk\Leap\Tests\Fixtures\TreeIndexResource;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A treeview index renders every level by asking indexRows() for the children of each
 * row it shows. Each of those asks was a query of its own, so a site with forty pages
 * opened its page index with forty-one queries. The whole tree is now fetched once and
 * handed out per parent.
 */
class ResourceTreeIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tree_index', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent')->nullable();
            $table->string('title');
            $table->integer('sort')->default(0);
        });
    }

    /**
     * Two roots, each with two children in reverse insert order, and a grandchild.
     *
     * @return array<string, TreeIndexModel>
     */
    private function seedTree(): array
    {
        $about = TreeIndexModel::create(['title' => 'About', 'sort' => 1]);
        $home = TreeIndexModel::create(['title' => 'Home', 'sort' => 0]);
        $team = TreeIndexModel::create(['title' => 'Team', 'parent' => $about->id, 'sort' => 1]);
        $history = TreeIndexModel::create(['title' => 'History', 'parent' => $about->id, 'sort' => 0]);
        $founders = TreeIndexModel::create(['title' => 'Founders', 'parent' => $history->id, 'sort' => 0]);
        $news = TreeIndexModel::create(['title' => 'News', 'parent' => $home->id, 'sort' => 0]);

        return compact('about', 'home', 'team', 'history', 'founders', 'news');
    }

    /**
     * Walk the tree the way resource-index.blade.php does.
     *
     * @return array<int, string>
     */
    private function render(TreeIndexResource $resource, ?int $parent = null, int $depth = 0): array
    {
        $lines = [];
        foreach ($resource->indexRows($parent) as $row) {
            $lines[] = str_repeat('-', $depth).$row['title'];
            $lines = array_merge($lines, $this->render($resource, $row['id'], $depth + 1));
        }

        return $lines;
    }

    public function test_the_whole_tree_costs_one_query(): void
    {
        $this->seedTree();

        DB::enableQueryLog();
        $this->render(new TreeIndexResource);
        $queries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'tree_index'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $queries, 'Rendering six rows over three levels must take a single query.');
    }

    public function test_every_row_lands_under_its_parent_in_order(): void
    {
        $this->seedTree();

        $this->assertSame(
            ['Home', '-News', 'About', '-History', '--Founders', '-Team'],
            $this->render(new TreeIndexResource),
        );
    }

    public function test_a_leaf_has_no_children(): void
    {
        ['founders' => $founders] = $this->seedTree();

        $this->assertCount(0, (new TreeIndexResource)->indexRows($founders->id));
    }

    /**
     * Searching keeps what it did when every level was queried on its own: a row shows
     * when it matches and so does every row above it.
     */
    public function test_search_still_applies_to_every_level(): void
    {
        $this->seedTree();

        $resource = new TreeIndexResource;
        $resource->search = 'o';

        $this->assertSame(['Home', 'About', '-History', '--Founders'], $this->render($resource));
    }

    /**
     * A filter offers the values of every level, not only those of the root rows: the
     * rows below them are filtered just the same.
     */
    public function test_a_filter_offers_the_values_of_every_level(): void
    {
        $this->seedTree();

        $resource = new TreeIndexResource;
        $attribute = $resource->indexAttributes()->where('name', 'title')->first();

        $this->assertEqualsCanonicalizing(
            ['Home', 'About', 'History', 'Team', 'Founders', 'News'],
            array_values($resource->filterData($attribute)),
        );
    }

    /**
     * The export is the whole table, subpages included, each with its parent id so the
     * tree can be read back from it.
     */
    public function test_the_csv_export_holds_every_level(): void
    {
        ['history' => $history] = $this->seedTree();

        ob_start();
        (new TreeIndexResource)->downloadCSVfile()->sendContent();
        $lines = array_map('str_getcsv', explode("\n", trim(ob_get_clean())));

        $this->assertSame(['title', 'parent', 'sort'], $lines[0]);
        $this->assertCount(7, $lines);
        $this->assertContains(['Founders', (string) $history->id, '0'], $lines);
    }
}
