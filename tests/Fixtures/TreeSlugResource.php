<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource over a tree model (TreeSlugModel has a "parent" column), for exercising
 * sibling-scoped slug uniqueness: the same slug may repeat under a different parent.
 */
class TreeSlugResource extends Resource
{
    public $model = TreeSlugModel::class;

    public function attributes(): array
    {
        return [
            Attribute::make('title')->index(1)->translatable(),
            Attribute::make('slug')->unique()->slugFrom('title'),
            Attribute::make('parent')->type('number'),
        ];
    }
}
