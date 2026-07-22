<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

/**
 * A resource with CSV import enabled, declared the way a module actually declares it:
 * the columns a file may hold and the attributes they fill, and nothing else.
 */
class ImportableResource extends Resource
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
            Attribute::make('title')->index(1)->searchable(),
        ];
    }
}
