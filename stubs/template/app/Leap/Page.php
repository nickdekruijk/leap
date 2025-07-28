<?php

namespace App\Leap;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Resource;

class Page extends Resource
{
    public function attributes()
    {
        return [
            Attribute::make('active')->switch()->label('Actief')->default(true),
            Attribute::make('menuitem')->index(3)->switch()->label('Toon in navigatie', 'Nav')->default(true),
            Attribute::make('title')->index(1)->searchable()->required()->slugify('slug')->label('Titel'),
            Attribute::make('parent')->tree($this)->label('Subpagina van'),
            Attribute::make('head')->index(2)->searchable(),
            Attribute::make('html_title')->searchable(),
            Attribute::make('id')->indexOnly(),
            Attribute::make('slug')->index()->searchable()->unique()->label('Slug'),
            Attribute::make('sort')->sortable(),
            Attribute::make('images')->media(),
            Attribute::make('sections')->label('Secties')->sections(
                Section::make('default')->label('Alleen tekst')->attributes(
                    Attribute::make('head')->required()->label('Kop'),
                    Attribute::make('body')->richtext(),
                ),
                Section::make('image-wide')->label('Afbeelding over gehele breedte')->attributes(
                    Attribute::make('image')->media(multiple: false)->required(),
                ),
                Section::make('image-left')->label('Afbeelding links')->attributes(
                    Attribute::make('image')->media()->required()->label('Afbeeldingen'),
                    Attribute::make('head')->required()->label('Kop'),
                    Attribute::make('body')->richtext(),
                ),
                Section::make('image-right')->label('Afbeelding rechts')->attributes(
                    Attribute::make('image')->media()->required()->label('Afbeeldingen'),
                    Attribute::make('head')->required()->label('Kop'),
                    Attribute::make('body')->richtext(),
                ),
            ),
        ];
    }

    public $icon = 'fas-sitemap';
    public $priority = -2;
    public $orderBy = 'sort';
    public $active = 'active';

    public $translations = [
        'nl' => 'Nederlands',
        'en' => 'English',
        'de' => [
            'nl' => 'Duits',
            'en' => 'German',
            'de' => 'Deutsch',
        ]
    ];

    public $title = [
        'en' => 'Website pages',
        'nl' => 'Website pagina\'s',
    ];
}
