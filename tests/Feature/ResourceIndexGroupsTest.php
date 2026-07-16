<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Resource;
use NickDeKruijk\Leap\Tests\Fixtures\IndexGroupsResource;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * An index group header is the first character of the ordered value, which only says
 * something when that value is text. Ordering by a date put every row of this century
 * under "2", an id grouped by its leading digit, and a select showed a label while
 * grouping by the key behind it. The guard was a single exception -- type != 'number'.
 */
class ResourceIndexGroupsTest extends TestCase
{
    private function resource(?string $orderBy): Resource
    {
        $resource = new IndexGroupsResource;
        $resource->orderBy = $orderBy;

        return $resource;
    }

    public function test_a_text_column_groups(): void
    {
        $this->assertTrue($this->resource('title')->indexGroupable());
    }

    public function test_an_email_column_groups(): void
    {
        $this->assertTrue($this->resource('email')->indexGroupable());
    }

    /**
     * The case that prompted this. An id is never given a type, so it keeps the 'text'
     * default and the old guard could not tell it from a title. getCasts() always
     * carries the primary key, which is what catches it.
     */
    public function test_the_primary_key_does_not_group(): void
    {
        $this->assertFalse($this->resource('id')->indexGroupable());
    }

    public function test_a_date_column_does_not_group(): void
    {
        $this->assertFalse($this->resource('published_at')->indexGroupable());
    }

    public function test_a_boolean_column_does_not_group(): void
    {
        $this->assertFalse($this->resource('active')->indexGroupable());
    }

    /**
     * The index renders a select's label, not the value it would group by, so the
     * headers spelled out the keys behind it: "1" over "Active".
     */
    public function test_a_select_column_does_not_group(): void
    {
        $this->assertFalse($this->resource('status')->indexGroupable());
    }

    /**
     * What the old type != 'number' guard did; it has to keep doing it.
     */
    public function test_a_number_column_does_not_group(): void
    {
        $this->assertFalse($this->resource('sort')->indexGroupable());
    }

    public function test_it_does_not_group_without_an_ordering(): void
    {
        $this->assertFalse($this->resource(null)->indexGroupable());
    }

    public function test_it_does_not_group_on_a_column_that_has_no_attribute(): void
    {
        $this->assertFalse($this->resource('nonexistent')->indexGroupable());
    }

    public function test_it_does_not_group_without_a_model(): void
    {
        $resource = $this->resource('title');
        $resource->model = null;

        $this->assertFalse($resource->indexGroupable());
    }

    /**
     * The gap the casts cannot see: an integer column that declares nothing, neither a
     * cast nor ->number(). Documented rather than fixed -- only the schema would know.
     */
    public function test_an_undeclared_integer_column_still_groups(): void
    {
        $this->assertTrue($this->resource('views')->indexGroupable());
    }
}
