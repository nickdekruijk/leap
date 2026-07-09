<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use NickDeKruijk\Settings\Setting;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Titles, slugs, descriptions and translatable section fields (head/body/
     * button) are seeded per locale (nl/en). When leap.locales is null the extra
     * locales are simply never shown.
     *
     * Note: the homepage uses the reserved slug "/" (not "home"), so it resolves
     * order-independently and is not also reachable under a second URL.
     */
    public function run(): void
    {
        Page::updateOrCreate(['id' => 1], [
            'title' => ['nl' => 'Home', 'en' => 'Home'],
            'slug' => ['nl' => '/', 'en' => '/'],
            'menuitem' => false,
            'sort' => 1,
            'description' => [
                'nl' => 'Welkom op de voorbeeldwebsite gebouwd met de leap-template.',
                'en' => 'Welcome to the example website built with the leap template.',
            ],
            'sections' => [
                [
                    '_name' => 'slide',
                    '_sort' => 0,
                    'active' => true,
                    'head' => ['nl' => 'Welkom', 'en' => 'Welcome'],
                    'body' => ['nl' => '<p>Een frisse start voor je nieuwe website.</p>', 'en' => '<p>A fresh start for your new website.</p>'],
                ],
                [
                    '_name' => 'slide',
                    '_sort' => 1,
                    'active' => true,
                    'head' => ['nl' => 'Volledig zelf te beheren', 'en' => 'Fully self-manageable'],
                    'body' => ['nl' => '<p>Beheer pagina\'s en secties in het adminpaneel.</p>', 'en' => '<p>Manage pages and sections in the admin panel.</p>'],
                ],
                [
                    '_name' => 'slide',
                    '_sort' => 2,
                    'active' => true,
                    'head' => ['nl' => 'Toegankelijk & snel', 'en' => 'Accessible & fast'],
                    'body' => ['nl' => '<p>Semantische HTML, responsive en zonder zware buildstap.</p>', 'en' => '<p>Semantic HTML, responsive and without a heavy build step.</p>'],
                ],
                // Every "default" (text-with-image) variant, one per image_position,
                // so the homepage shows all layouts at once. Add real images per
                // section in the admin panel; without one the text spans full width.
                [
                    '_name' => 'default',
                    '_sort' => 3,
                    'active' => true,
                    'head' => ['nl' => 'Afbeelding links', 'en' => 'Image left'],
                    'image_position' => 'left',
                    'body' => ['nl' => '<p>Tekst met een vierkante afbeelding links ernaast.</p>', 'en' => '<p>Text with a square image to its left.</p>'],
                ],
                [
                    '_name' => 'default',
                    '_sort' => 4,
                    'active' => true,
                    'head' => ['nl' => 'Afbeelding rechts', 'en' => 'Image right'],
                    'image_position' => 'right',
                    'body' => ['nl' => '<p>Tekst met een vierkante afbeelding rechts ernaast.</p>', 'en' => '<p>Text with a square image to its right.</p>'],
                ],
                [
                    '_name' => 'default',
                    '_sort' => 5,
                    'active' => true,
                    'head' => ['nl' => 'Afbeelding links, rond', 'en' => 'Image left, round'],
                    'image_position' => 'left round',
                    'body' => ['nl' => '<p>Zelfde layout, maar met een ronde afbeelding.</p>', 'en' => '<p>Same layout, but with a round image.</p>'],
                ],
                [
                    '_name' => 'default',
                    '_sort' => 6,
                    'active' => true,
                    'head' => ['nl' => 'Afbeelding rechts, rond', 'en' => 'Image right, round'],
                    'image_position' => 'right round',
                    'body' => ['nl' => '<p>Een ronde afbeelding rechts van de tekst.</p>', 'en' => '<p>A round image to the right of the text.</p>'],
                ],
                [
                    '_name' => 'default',
                    '_sort' => 7,
                    'active' => true,
                    'head' => ['nl' => 'Breedbeeld', 'en' => 'Wide'],
                    'image_position' => 'bottom wide',
                    'body' => ['nl' => '<p>Een breedbeeld-afbeelding onder de tekst, over de volle breedte.</p>', 'en' => '<p>A full-width wide image below the text.</p>'],
                ],
                [
                    '_name' => 'quote',
                    '_view' => 'sections.default',
                    '_sort' => 8,
                    'active' => true,
                    'head' => ['nl' => 'Een goede website verkoopt zichzelf.', 'en' => 'A good website sells itself.'],
                    'body' => ['nl' => 'Een tevreden klant', 'en' => 'A happy customer'],
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 9,
                    'active' => true,
                    'head' => ['nl' => 'Eerste highlight', 'en' => 'First highlight'],
                    'body' => ['nl' => '<p>Opeenvolgende highlights vormen samen één horizontaal scrollende rij kaarten.</p>', 'en' => '<p>Consecutive highlights form one horizontally scrolling row of cards.</p>'],
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 10,
                    'active' => true,
                    'head' => ['nl' => 'Tweede highlight', 'en' => 'Second highlight'],
                    'body' => ['nl' => '<p>Sleep of gebruik de pijlen om te scrollen.</p>', 'en' => '<p>Drag or use the arrows to scroll.</p>'],
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 11,
                    'active' => true,
                    'head' => ['nl' => 'Derde highlight', 'en' => 'Third highlight'],
                    'body' => ['nl' => '<p>Elke kaart heeft een optionele afbeelding, kop, tekst en knop.</p>', 'en' => '<p>Each card has an optional image, heading, text and button.</p>'],
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 12,
                    'active' => true,
                    'head' => ['nl' => 'Vierde highlight', 'en' => 'Fourth highlight'],
                    'body' => ['nl' => '<p>De rij scrollt horizontaal zodra er meer kaarten zijn dan passen.</p>', 'en' => '<p>The row scrolls horizontally once there are more cards than fit.</p>'],
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 13,
                    'active' => true,
                    'head' => ['nl' => 'Vijfde highlight', 'en' => 'Fifth highlight'],
                    'body' => ['nl' => '<p>Op touchscreens kun je gewoon swipen.</p>', 'en' => '<p>On touchscreens you can simply swipe.</p>'],
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 14,
                    'active' => true,
                    'head' => ['nl' => 'Zesde highlight', 'en' => 'Sixth highlight'],
                    'body' => ['nl' => '<p>Met het toetsenbord: focus de rij en gebruik de pijltjestoetsen.</p>', 'en' => '<p>With the keyboard: focus the row and use the arrow keys.</p>'],
                ],
                [
                    '_name' => 'cta',
                    '_view' => 'sections.default',
                    '_sort' => 15,
                    'active' => true,
                    'head' => ['nl' => 'Klaar om te beginnen?', 'en' => 'Ready to get started?'],
                    'body' => ['nl' => '<p><a class="button" href="/contact">Neem contact op</a></p>', 'en' => '<p><a class="button" href="/en/contact">Get in touch</a></p>'],
                ],
            ],
        ]);

        Page::updateOrCreate(['id' => 2], [
            'title' => ['nl' => 'Over ons', 'en' => 'About us'],
            'slug' => ['nl' => 'over-ons', 'en' => 'about-us'],
            'sort' => 2,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => ['nl' => 'Over ons', 'en' => 'About us'],
                    'image_position' => 'left',
                    'body' => ['nl' => '<p>Vertel hier het verhaal achter je organisatie.</p>', 'en' => '<p>Tell the story behind your organisation here.</p>'],
                ],
            ],
        ]);

        Page::updateOrCreate(['id' => 3], [
            'title' => ['nl' => 'Diensten', 'en' => 'Services'],
            'parent' => 2,
            'slug' => ['nl' => 'diensten', 'en' => 'services'],
            'sort' => 1,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => ['nl' => 'Wat we doen', 'en' => 'What we do'],
                    'body' => ['nl' => '<p>Een voorbeeld van een subpagina onder "Over ons".</p>', 'en' => '<p>An example of a subpage under "About us".</p>'],
                ],
            ],
        ]);

        Page::updateOrCreate(['id' => 4], [
            'title' => ['nl' => 'Contact', 'en' => 'Contact'],
            'slug' => ['nl' => 'contact', 'en' => 'contact'],
            'sort' => 3,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => ['nl' => 'Contact', 'en' => 'Contact'],
                    'body' => ['nl' => '<p>Zet hier je contactgegevens of een formulier.</p>', 'en' => '<p>Put your contact details or a form here.</p>'],
                ],
            ],
        ]);

        // Legal pages, linked from the footer but hidden from the main navigation
        Page::updateOrCreate(['id' => 5], [
            'title' => ['nl' => 'Privacybeleid', 'en' => 'Privacy policy'],
            'slug' => ['nl' => 'privacy', 'en' => 'privacy'],
            'menuitem' => false,
            'sort' => 4,
            'sections' => [
                ['_name' => 'default', '_sort' => 0, 'active' => true, 'head' => ['nl' => 'Privacybeleid', 'en' => 'Privacy policy'], 'body' => ['nl' => '<p>Beschrijf hier hoe je met persoonsgegevens omgaat.</p>', 'en' => '<p>Describe how you handle personal data here.</p>']],
            ],
        ]);

        Page::updateOrCreate(['id' => 6], [
            'title' => ['nl' => 'Algemene voorwaarden', 'en' => 'Terms & conditions'],
            'slug' => ['nl' => 'algemene-voorwaarden', 'en' => 'terms'],
            'menuitem' => false,
            'sort' => 5,
            'sections' => [
                ['_name' => 'default', '_sort' => 0, 'active' => true, 'head' => ['nl' => 'Algemene voorwaarden', 'en' => 'Terms & conditions'], 'body' => ['nl' => '<p>Zet hier je algemene voorwaarden.</p>', 'en' => '<p>Put your terms and conditions here.</p>']],
            ],
        ]);

        // Default footer settings (only when the settings package is installed).
        // socials and footer_links use "label:url" per line (the ':' key separator of setting_array()).
        if (class_exists(Setting::class)) {
            Setting::set([
                'footer_contact' => [
                    'value' => "Voorbeeldstraat 1\n1234 AB Amsterdam\ninfo@example.com",
                    'description' => 'Adres/contactgegevens in de footer',
                ],
                'socials' => [
                    'value' => "instagram:https://instagram.com\nlinkedin:https://linkedin.com\nfacebook:https://facebook.com",
                    'description' => 'Social media, één "naam:url" per regel (naam = FontAwesome brand-icoon)',
                ],
                'footer_copyright' => [
                    'value' => '© '.date('Y').' '.config('app.name'),
                    'description' => 'Copyright-regel onderaan de footer',
                ],
                'footer_links' => [
                    'value' => "Privacy:/privacy\nAlgemene voorwaarden:/algemene-voorwaarden",
                    'description' => 'Footer-links, één "label:url" per regel',
                ],
                'og_image' => [
                    'value' => '',
                    'description' => 'Standaard social-share afbeelding (URL of /storage-pad); pagina-eigen afbeelding gaat voor',
                ],
            ]);
        }
    }
}
