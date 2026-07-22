<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource whose slug field derives from a translatable title via slugFrom(), for
 * exercising the editor's slug-placeholder logic. No validation rules, so the editor's
 * updated() hook can be driven without a mounted Livewire component.
 */
class SlugResource extends Resource
{
    public $model = Article::class;

    public function attributes(): array
    {
        return [
            Attribute::make('title')->index(1)->translatable(),
            Attribute::make('slug')->slugFrom('title'),
        ];
    }
}
