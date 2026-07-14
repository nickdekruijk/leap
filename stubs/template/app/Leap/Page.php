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
            Attribute::make('title')->index(1)->searchable()->required()->label(['nl' => 'Titel', 'en' => 'Title']),
            Attribute::make('parent')->tree($this)->label(['nl' => 'Subpagina van', 'en' => 'Subpage of']),
            Attribute::make('html_title')->searchable()
                ->label(['nl' => 'HTML-titel', 'en' => 'HTML title'])
                ->placeholder(['nl' => 'Leeg = paginatitel', 'en' => 'Empty = page title'])
                ->hint(['nl' => 'Voor SEO: de titel in de browsertab en zoekresultaten. Leeg laten gebruikt de paginatitel.', 'en' => 'For SEO: the title in the browser tab and search results. Leave empty to use the page title.']),
            Attribute::make('description')->textarea()
                ->label(['nl' => 'Omschrijving', 'en' => 'Description'])
                ->hint(['nl' => 'Voor SEO: de meta-omschrijving voor Google en social media (±150 tekens).', 'en' => 'For SEO: the meta description for Google and social media (~150 characters).']),
            Attribute::make('id')->indexOnly(),
            Attribute::make('slug')->index()->searchable()->unique()->slugFrom('title')->label('Slug'),
            Attribute::make('sort')->sortable(),
            Attribute::make('images')->media(),
            Attribute::make('sections')->label(['nl' => 'Secties', 'en' => 'Sections'])->sections(
                Section::make('slide')->label(['nl' => 'Slide (carousel)', 'en' => 'Slide (carousel)'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('white_text')->switch()->label(['nl' => 'Witte tekst (voor op donkere achtergrond)', 'en' => 'White text (for dark backgrounds)'])->default(false),
                    Attribute::make('head')->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                    Attribute::make('image')->media(multiple: false)->required()->label(['nl' => 'Afbeelding of video (.mp4)', 'en' => 'Image or video (.mp4)']),
                    Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text'])->translatable(),
                ),
                Section::make('default')->label(['nl' => 'Tekst met afbeelding', 'en' => 'Text with image'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('menuitem')->switch()->label(['nl' => 'Kop tonen in navigatie', 'en' => 'Show heading in navigation'])->default(false),
                    Attribute::make('head')->required()->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                    Attribute::make('image_position')->default('right')->label(['nl' => 'Positie afbeelding', 'en' => 'Image position'])->select()->values([
                        'none' => 'Geen afbeelding',
                        'left' => 'Links vierkant',
                        'right' => 'Rechts vierkant',
                        'bottom wide' => 'Breedbeeld (onder tekst)',
                    ]),
                    Attribute::make('image')->media()->label(['nl' => 'Afbeelding(en)', 'en' => 'Image(s)']),
                    Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text'])->translatable(),
                    Attribute::make('dark_background')->switch()->label(['nl' => 'Donkere achtergrond (witte tekst)', 'en' => 'Dark background (white text)'])->default(false),
                    Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
                ),
                Section::make('highlights')->label(['nl' => 'Highlights (horizontaal scrollend)', 'en' => 'Highlights (horizontal scroll)'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('head')->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                    Attribute::make('image')->media(multiple: false)->label(['nl' => 'Afbeelding (optioneel)', 'en' => 'Image (optional)']),
                    Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text'])->translatable(),
                    Attribute::make('button')->label(['nl' => 'Knop tekst (optioneel)', 'en' => 'Button text (optional)'])->translatable(),
                    Attribute::make('button_link')->label(['nl' => 'Knop link, bijv "/contact"', 'en' => 'Button link, e.g. "/contact"'])->translatable(),
                    Attribute::make('dark_background')->switch()->label(['nl' => 'Donkere achtergrond (witte tekst)', 'en' => 'Dark background (white text)'])->default(false),
                    Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
                ),
                Section::make('cta')->view('sections.default')->label(['nl' => 'Call to action', 'en' => 'Call to action'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('head')->required()->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                    Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text'])->translatable(),
                    Attribute::make('dark_background')->switch()->label(['nl' => 'Donkere achtergrond (witte tekst)', 'en' => 'Dark background (white text)'])->default(false),
                    Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
                ),
                Section::make('quote')->view('sections.default')->label(['nl' => 'Quote', 'en' => 'Quote'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('head')->required()->label(['nl' => 'Quote', 'en' => 'Quote'])->sectionTitle()->translatable(),
                    Attribute::make('body')->label(['nl' => 'Van', 'en' => 'From'])->sectionTitle()->translatable(),
                    Attribute::make('dark_background')->switch()->label(['nl' => 'Donkere achtergrond (witte tekst)', 'en' => 'Dark background (white text)'])->default(false),
                    Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
                ),
                Section::make('video')->label(['nl' => 'Video (breedbeeld)', 'en' => 'Video (full width)'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('head')->label(['nl' => 'Kop (voor schermlezers)', 'en' => 'Heading (for screen readers)'])->sectionTitle()->translatable(),
                    Attribute::make('video_id')->required()
                        ->label(['nl' => 'Video-ID (YouTube of Vimeo)', 'en' => 'Video ID (YouTube or Vimeo)'])
                        ->hint([
                            'nl' => 'YouTube: het deel na "?v=" in de URL, bijv. dQw4w9WgXcQ. Vimeo: het nummer in de URL, bijv. 1084537.',
                            'en' => 'YouTube: the part after "?v=" in the URL, e.g. dQw4w9WgXcQ. Vimeo: the number in the URL, e.g. 1084537.',
                        ]),
                    Attribute::make('image')->media(multiple: false)
                        ->label(['nl' => 'Poster-afbeelding (optioneel)', 'en' => 'Poster image (optional)'])
                        ->hint([
                            'nl' => 'Leeg = de poster wordt automatisch bij YouTube/Vimeo opgehaald en lokaal opgeslagen. Wordt getoond tot er op play wordt geklikt; de video zelf laadt pas daarna.',
                            'en' => 'Empty = the poster is fetched from YouTube/Vimeo and stored locally. Shown until play is clicked; the video itself only loads after that.',
                        ]),
                ),
                // Renders the cookie registry from config('leap.consent') on the privacy
                // page, so it cannot drift away from the cookies the site actually sets.
                Section::make('cookies')->label(['nl' => 'Cookie-overzicht', 'en' => 'Cookie overview'])->attributes(
                    Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                    Attribute::make('head')->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                    Attribute::make('body')->richtext()->label(['nl' => 'Inleidende tekst', 'en' => 'Introduction'])->translatable(),
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
