<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource with a required translatable title, for exercising the editor's per-locale
 * validation rules — a required translatable field must be filled in at least one locale.
 */
class RequiredTitleResource extends Resource
{
    public $model = Article::class;

    public function attributes(): array
    {
        return [
            Attribute::make('title')->index(1)->translatable()->required(),
        ];
    }
}
