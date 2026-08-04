<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Resource;

/**
 * A resource with a sections attribute, for testing what the editor makes of the JSON
 * that is already in the column. The section carries one of each kind that matters:
 * translatable text, a switch, a select, and a json field — the last one to prove it is
 * left alone by anything that collapses per-locale arrays.
 */
class SectionsResource extends Resource
{
    public $model = SectionsModel::class;

    public function attributes(): array
    {
        return [
            Attribute::make('id')->indexOnly(),
            Attribute::make('title')->index(1),
            Attribute::make('sections')->sections(
                Section::make('block')->attributes(
                    Attribute::make('active')->switch()->default(true),
                    Attribute::make('head')->sectionTitle()->translatable(),
                    Attribute::make('body')->richtext()->translatable(),
                    Attribute::make('layout')->select()->values(['left' => 'Left', 'right' => 'Right']),
                    Attribute::make('meta')->json(),
                ),
            ),
        ];
    }
}
