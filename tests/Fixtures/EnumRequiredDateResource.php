<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;

/**
 * EnumResource with a datetime that has to be filled in, to show that a required date
 * still refuses to be empty.
 */
class EnumRequiredDateResource extends EnumResource
{
    public $slug = 'enums-required';

    public function attributes(): array
    {
        return [
            Attribute::make('id')->indexOnly(),
            Attribute::make('title')->index(1),
            Attribute::make('sent_at')->datetime()->required(),
        ];
    }
}
