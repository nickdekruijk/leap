<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource with a treeview over TreeIndexModel, ordered by "sort" like a page tree.
 */
class TreeIndexResource extends Resource
{
    public $model = TreeIndexModel::class;

    public $orderBy = 'sort';

    public function attributes(): array
    {
        return [
            Attribute::make('title')->index(1)->searchable(),
            Attribute::make('parent')->tree($this),
            Attribute::make('sort')->sortable(),
        ];
    }
}
