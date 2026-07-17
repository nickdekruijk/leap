<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource with a filterable pivot, foreign and plain text column, for exercising the
 * index filters.
 */
class FilterResource extends Resource
{
    public $model = FilterModel::class;

    /**
     * Redeclared without the parent's #[Locked] so a test can order the index.
     *
     * @var string|null
     */
    public $orderBy = 'id';

    public function attributes(): array
    {
        return [
            Attribute::make('id')->indexOnly(),
            Attribute::make('title')->index(1)->filterable(),
            Attribute::make('tags')->pivot(FilterTag::class, index: 'name')->index(2)->filterable(),
            Attribute::make('author_id')->foreign(FilterAuthor::class, index: 'name')->index(3)->filterable(),
        ];
    }
}
