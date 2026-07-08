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
            Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
            Attribute::make('menuitem')->index(3)->switch()->label(['nl' => 'Toon in navigatie', 'en' => 'Show in navigation'], 'Nav')->default(true),
            Attribute::make('title')->index(1)->searchable()->required()->slugify('slug')->label(['nl' => 'Titel', 'en' => 'Title']),
            Attribute::make('parent')->tree($this)->label(['nl' => 'Subpagina van', 'en' => 'Subpage of']),
            Attribute::make('head')->index(2)->searchable(),
            Attribute::make('html_title')->searchable(),
            Attribute::make('id')->indexOnly(),
            Attribute::make('slug')->index()->searchable()->unique()->label('Slug'),
            Attribute::make('sort')->sortable(),
            Attribute::make('images')->media(),
            Attribute::make('sections')->label(['nl' => 'Secties', 'en' => 'Sections'])->sections(
                Section::make('slide')->label(['nl' => 'Slide (carousel)', 'en' => 'Slide (carousel)'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('white_text')->switch()->label(['nl' => 'Witte tekst (voor op donkere achtergrond)', 'en' => 'White text (for dark backgrounds)'])->default(false),
                    Attribute::make('head')->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle(),
                    Attribute::make('image')->media(multiple: false)->required()->label(['nl' => 'Afbeelding of video (.mp4)', 'en' => 'Image or video (.mp4)']),
                    Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text']),
                ),
                Section::make('default')->label(['nl' => 'Tekst met afbeelding', 'en' => 'Text with image'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('menuitem')->switch()->label(['nl' => 'Kop tonen in navigatie', 'en' => 'Show heading in navigation'])->default(false),
                    Attribute::make('head')->required()->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle(),
                    Attribute::make('image')->media()->label(['nl' => 'Afbeelding(en)', 'en' => 'Image(s)']),
                    Attribute::make('image_position')->default('right')->label(['nl' => 'Positie en vorm afbeelding', 'en' => 'Image position and shape'])->select()->values([
                        'left' => 'Links vierkant',
                        'right' => 'Rechts vierkant',
                        'left round' => 'Links rond',
                        'right round' => 'Rechts rond',
                        'bottom wide' => 'Breedbeeld (onder tekst)',
                    ]),
                    Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text']),
                    Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
                ),
                Section::make('highlights')->label(['nl' => 'Highlights (horizontaal scrollend)', 'en' => 'Highlights (horizontal scroll)'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('head')->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle(),
                    Attribute::make('image')->media(multiple: false)->label(['nl' => 'Afbeelding (optioneel)', 'en' => 'Image (optional)']),
                    Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text']),
                    Attribute::make('button')->label(['nl' => 'Knop tekst (optioneel)', 'en' => 'Button text (optional)']),
                    Attribute::make('button_link')->label(['nl' => 'Knop link, bijv "/contact"', 'en' => 'Button link, e.g. "/contact"']),
                    Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
                ),
                Section::make('cta')->view('sections.default')->label(['nl' => 'Call to action', 'en' => 'Call to action'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('head')->required()->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle(),
                    Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text']),
                    Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
                ),
                Section::make('quote')->view('sections.default')->label(['nl' => 'Quote', 'en' => 'Quote'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('head')->required()->label(['nl' => 'Quote', 'en' => 'Quote'])->sectionTitle(),
                    Attribute::make('body')->label(['nl' => 'Van', 'en' => 'From'])->sectionTitle(),
                    Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
                ),
            ),
        ];
    }

    public $icon = 'fas-sitemap';

    public $priority = -2;

    public $orderBy = 'sort';

    public $active = 'active';

    public $title = [
        'nl' => 'Website pagina\'s',
        'en' => 'Website pages',
    ];
}
