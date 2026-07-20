<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource over a translatable model, for anything that has to know whether the editor
 * is multilingual — editorLocales() answers with the configured locales only when the
 * module's model has translatable attributes.
 */
class ArticleResource extends Resource
{
    public $model = Article::class;

    public function attributes(): array
    {
        return [
            Attribute::make('id')->indexOnly(),
            Attribute::make('title')->index(1)->translatable(),
        ];
    }
}
