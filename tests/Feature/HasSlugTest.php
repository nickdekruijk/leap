<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Tests\Fixtures\FlatSlugModel;
use NickDeKruijk\Leap\Tests\Fixtures\TreeSlugModel;
use NickDeKruijk\Leap\Tests\TestCase;

class HasSlugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('flat_slugs', function (Blueprint $table): void {
            $table->id();
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('tree_slugs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent')->nullable();
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->timestamps();
        });
    }

    private function slug($model): string
    {
        return $model->getTranslation('slug', app()->getLocale(), false);
    }

    public function test_a_flat_model_gets_globally_unique_slugs(): void
    {
        // No "parent" column: the query must not reference one, and uniqueness is global.
        $first = FlatSlugModel::create(['title' => 'News']);
        $second = FlatSlugModel::create(['title' => 'News']);

        $this->assertSame('news', $this->slug($first));
        $this->assertSame('news-2', $this->slug($second));
    }

    public function test_a_tree_model_scopes_uniqueness_to_siblings(): void
    {
        $rootA = TreeSlugModel::create(['title' => 'Root A']);
        $rootB = TreeSlugModel::create(['title' => 'Root B']);

        $childA = TreeSlugModel::create(['title' => 'News', 'parent' => $rootA->id]);
        $childB = TreeSlugModel::create(['title' => 'News', 'parent' => $rootB->id]);

        // The same slug is allowed under different parents...
        $this->assertSame('news', $this->slug($childA));
        $this->assertSame('news', $this->slug($childB));

        // ...but a collision with a sibling is de-duplicated.
        $siblingClash = TreeSlugModel::create(['title' => 'News', 'parent' => $rootA->id]);
        $this->assertSame('news-2', $this->slug($siblingClash));
    }
}
