<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource with one attribute of every shape an index can be ordered by, for
 * exercising Resource::indexGroupable() and the group header the index blade renders
 * from it.
 */
class IndexGroupsResource extends Resource
{
    public $model = IndexGroupsModel::class;

    /**
     * Redeclared without the parent's #[Locked] so a test can order the index, the way
     * a generated resource redeclares it to set its own default.
     *
     * @var string|null
     */
    public $orderBy = 'title';

    public function attributes(): array
    {
        return [
            Attribute::make('id')->indexOnly(),
            Attribute::make('title')->index(1),
            Attribute::make('email')->email()->index(2),
            Attribute::make('published_at')->datetime()->index(3),
            Attribute::make('active')->switch()->index(4),
            Attribute::make('status')->select()->values([1 => 'Active', 2 => 'Inactive'])->index(5),
            Attribute::make('sort')->number()->index(6),
            Attribute::make('views')->index(7),
        ];
    }
}
