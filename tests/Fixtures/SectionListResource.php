<?php

namespace NickDeKruijk\Leap\Tests\Fixtures;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Resource;

/**
 * A resource shaped like a long page, for testing the list of sections itself: a divider
 * that groups what follows it, a section with a title field, one with nothing but rich
 * text, and one with an image.
 */
class SectionListResource extends Resource
{
    public $model = SectionsModel::class;

    public function attributes(): array
    {
        return [
            Attribute::make('id')->indexOnly(),
            Attribute::make('title')->index(1),
            Attribute::make('sections')->sections(
                Section::make('tab')->divider()->attributes(
                    Attribute::make('head')->sectionTitle(),
                ),
                Section::make('block')->attributes(
                    Attribute::make('active')->switch()->default(true),
                    Attribute::make('head')->sectionTitle()->translatable(),
                    Attribute::make('body')->richtext()->translatable(),
                ),
                Section::make('text')->attributes(
                    Attribute::make('active')->switch()->default(true),
                    Attribute::make('layout')->select()->values(['left' => 'Left', 'right' => 'Right']),
                    Attribute::make('body')->richtext()->translatable(),
                ),
                Section::make('image')->attributes(
                    Attribute::make('image')->media(),
                    Attribute::make('caption'),
                ),
            ),
        ];
    }
}
