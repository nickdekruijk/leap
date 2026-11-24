<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource with a date and a datetime column in its index.
 */
class DatedResource extends Resource
{
    public $model = DatedModel::class;

    public function attributes(): array
    {
        return [
            Attribute::make('starts_on')->date()->index(1),
            Attribute::make('received_at')->datetime()->index(2),
            Attribute::make('id')->indexOnly(),
        ];
    }
}
