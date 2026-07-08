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
                Section::make('slide')->label('Slide (carousel)')->attributes(
                    Attribute::make('active')->switch()->label('Actief')->default(true),
                    Attribute::make('white_text')->switch()->label('Witte tekst (voor op donkere achtergrond)')->default(false),
                    Attribute::make('head')->label('Kop')->sectionTitle(),
                    Attribute::make('image')->media(multiple: false)->required()->label('Afbeelding of video (.mp4)'),
                    Attribute::make('body')->richtext()->label('Tekst'),
                ),
                Section::make('default')->label('Tekst met afbeelding')->attributes(
                    Attribute::make('active')->switch()->label('Actief')->default(true),
                    Attribute::make('menuitem')->switch()->label('Kop tonen in navigatie')->default(false),
                    Attribute::make('head')->required()->label('Kop')->sectionTitle(),
                    Attribute::make('image')->media()->label('Afbeelding(en)'),
                    Attribute::make('image_position')->default('right')->label('Positie en vorm afbeelding')->select()->values([
                        'left' => 'Links vierkant',
                        'right' => 'Rechts vierkant',
                        'left round' => 'Links rond',
                        'right round' => 'Rechts rond',
                        'bottom wide' => 'Breedbeeld (onder tekst)',
                    ]),
                    Attribute::make('body')->richtext()->label('Tekst'),
                    Attribute::make('background')->media(multiple: false)->label('Achtergrondfoto (optioneel)'),
                ),
                Section::make('highlights')->label('Highlights (horizontaal scrollend)')->attributes(
                    Attribute::make('active')->switch()->label('Actief')->default(true),
                    Attribute::make('head')->label('Kop')->sectionTitle(),
                    Attribute::make('image')->media(multiple: false)->label('Afbeelding (optioneel)'),
                    Attribute::make('body')->richtext()->label('Tekst'),
                    Attribute::make('button')->label('Knop tekst (optioneel)'),
                    Attribute::make('button_link')->label('Knop link, bijv "/contact"'),
                    Attribute::make('background')->media(multiple: false)->label('Achtergrondfoto (optioneel)'),
                ),
                Section::make('cta')->view('sections.default')->label('Call to action')->attributes(
                    Attribute::make('active')->switch()->label('Actief')->default(true),
                    Attribute::make('head')->required()->label('Kop')->sectionTitle(),
                    Attribute::make('body')->richtext()->label('Tekst'),
                    Attribute::make('background')->media(multiple: false)->label('Achtergrondfoto (optioneel)'),
                ),
                Section::make('quote')->view('sections.default')->label('Quote')->attributes(
                    Attribute::make('active')->switch()->label('Actief')->default(true),
                    Attribute::make('head')->required()->label('Quote')->sectionTitle(),
                    Attribute::make('body')->label('Van')->sectionTitle(),
                    Attribute::make('background')->media(multiple: false)->label('Achtergrondfoto (optioneel)'),
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
