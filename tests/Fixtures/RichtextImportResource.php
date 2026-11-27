<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource that takes a rich-text column from a CSV file. An import writes to the
 * model itself, so it is a second way in for a value the editor would have filtered.
 */
class RichtextImportResource extends Resource
{
    public $model = Article::class;

    /**
     * @var array<string, array<int, string>>
     */
    public array $allowImport = [
        'columns' => ['title'],
        'attributes' => ['title'],
    ];

    public function attributes(): array
    {
        return [
            Attribute::make('id')->indexOnly(),
            Attribute::make('title')->index(1)->richtext(),
        ];
    }
}
