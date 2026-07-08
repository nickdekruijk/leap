<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Note: the homepage uses the reserved slug "/" (not "home"), so it resolves
     * order-independently and is not also reachable under a second URL. See the
     * PageController::getPages() homepage detection.
     */
    public function run(): void
    {
        Page::updateOrCreate(['id' => 1], [
            'title' => 'Home',
            'slug' => '/',
            'menuitem' => false,
            'sort' => 1,
            'sections' => [
                [
                    '_name' => 'slide',
                    '_sort' => 0,
                    'active' => true,
                    'head' => 'Welkom',
                    'body' => '<p>Een frisse start voor je nieuwe website.</p>',
                ],
                [
                    '_name' => 'slide',
                    '_sort' => 1,
                    'active' => true,
                    'head' => 'Volledig zelf te beheren',
                    'body' => '<p>Beheer pagina\'s en secties in het adminpaneel.</p>',
                ],
                [
                    '_name' => 'slide',
                    '_sort' => 2,
                    'active' => true,
                    'head' => 'Toegankelijk & snel',
                    'body' => '<p>Semantische HTML, responsive en zonder zware buildstap.</p>',
                ],
                [
                    '_name' => 'default',
                    '_sort' => 3,
                    'active' => true,
                    'head' => 'Tekst met afbeelding',
                    'image_position' => 'right',
                    'body' => '<p>Deze sectie combineert tekst met een afbeelding links, rechts, rond of breedbeeld.</p>',
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 4,
                    'active' => true,
                    'head' => 'Eerste highlight',
                    'body' => '<p>Opeenvolgende highlights vormen samen één horizontaal scrollende rij kaarten.</p>',
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 5,
                    'active' => true,
                    'head' => 'Tweede highlight',
                    'body' => '<p>Sleep of gebruik de pijlen om te scrollen.</p>',
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 6,
                    'active' => true,
                    'head' => 'Derde highlight',
                    'body' => '<p>Elke kaart heeft een optionele afbeelding, kop, tekst en knop.</p>',
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 7,
                    'active' => true,
                    'head' => 'Vierde highlight',
                    'body' => '<p>De rij scrollt horizontaal zodra er meer kaarten zijn dan passen.</p>',
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 8,
                    'active' => true,
                    'head' => 'Vijfde highlight',
                    'body' => '<p>Op touchscreens kun je gewoon swipen.</p>',
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 9,
                    'active' => true,
                    'head' => 'Zesde highlight',
                    'body' => '<p>Met het toetsenbord: focus de rij en gebruik de pijltjestoetsen.</p>',
                ],
                [
                    '_name' => 'cta',
                    '_view' => 'sections.default',
                    '_sort' => 10,
                    'active' => true,
                    'head' => 'Klaar om te beginnen?',
                    'body' => '<p><a class="button" href="/contact">Neem contact op</a></p>',
                ],
            ],
        ]);

        Page::updateOrCreate(['id' => 2], [
            'title' => 'Over ons',
            'slug' => 'over-ons',
            'sort' => 2,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => 'Over ons',
                    'image_position' => 'left',
                    'body' => '<p>Vertel hier het verhaal achter je organisatie.</p>',
                ],
            ],
        ]);

        Page::updateOrCreate(['id' => 3], [
            'title' => 'Diensten',
            'parent' => 2,
            'slug' => 'diensten',
            'sort' => 1,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => 'Wat we doen',
                    'body' => '<p>Een voorbeeld van een subpagina onder "Over ons".</p>',
                ],
            ],
        ]);

        Page::updateOrCreate(['id' => 4], [
            'title' => 'Contact',
            'slug' => 'contact',
            'sort' => 3,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => 'Contact',
                    'body' => '<p>Zet hier je contactgegevens of een formulier.</p>',
                ],
            ],
        ]);

        // Legal pages, linked from the footer but hidden from the main navigation
        Page::updateOrCreate(['id' => 5], [
            'title' => 'Privacybeleid',
            'slug' => 'privacy',
            'menuitem' => false,
            'sort' => 4,
            'sections' => [
                ['_name' => 'default', '_sort' => 0, 'active' => true, 'head' => 'Privacybeleid', 'body' => '<p>Beschrijf hier hoe je met persoonsgegevens omgaat.</p>'],
            ],
        ]);

        Page::updateOrCreate(['id' => 6], [
            'title' => 'Algemene voorwaarden',
            'slug' => 'algemene-voorwaarden',
            'menuitem' => false,
            'sort' => 5,
            'sections' => [
                ['_name' => 'default', '_sort' => 0, 'active' => true, 'head' => 'Algemene voorwaarden', 'body' => '<p>Zet hier je algemene voorwaarden.</p>'],
            ],
        ]);

        // Default footer settings (only when the settings package is installed).
        // socials and footer_links use "label:url" per line (the ':' key separator of setting_array()).
        if (class_exists(\NickDeKruijk\Settings\Setting::class)) {
            \NickDeKruijk\Settings\Setting::set([
                'footer_contact' => "Voorbeeldstraat 1\n1234 AB Amsterdam\ninfo@example.com",
                'socials' => "instagram:https://instagram.com\nlinkedin:https://linkedin.com\nfacebook:https://facebook.com",
                'footer_copyright' => '© '.date('Y').' '.config('app.name'),
                'footer_links' => "Privacy:/privacy\nAlgemene voorwaarden:/algemene-voorwaarden",
            ]);
        }
    }
}
