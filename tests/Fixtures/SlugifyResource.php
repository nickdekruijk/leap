<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * The older declaration: slugify() sits on the source field and names its target, so the
 * slug field itself carries no sign that it is one. Projects written before slugFrom()
 * existed look like this.
 */
class SlugifyResource extends Resource
{
    public $model = Article::class;

    public function attributes(): array
    {
        return [
            Attribute::make('title')->index(1)->slugify('slug'),
            Attribute::make('slug')->index(),
        ];
    }
}
