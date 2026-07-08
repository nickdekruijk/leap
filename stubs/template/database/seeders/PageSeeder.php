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
                    '_name' => 'default',
                    '_sort' => 1,
                    'active' => true,
                    'head' => 'Tekst met afbeelding',
                    'image_position' => 'right',
                    'body' => '<p>Deze sectie combineert tekst met een afbeelding links, rechts, rond of breedbeeld.</p>',
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 2,
                    'active' => true,
                    'head' => 'Eerste highlight',
                    'body' => '<p>Opeenvolgende highlights vormen samen één horizontaal scrollende rij kaarten.</p>',
                ],
                [
                    '_name' => 'highlights',
                    '_sort' => 3,
                    'active' => true,
                    'head' => 'Tweede highlight',
                    'body' => '<p>Sleep of gebruik de pijlen om te scrollen.</p>',
                ],
                [
                    '_name' => 'cta',
                    '_sort' => 4,
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
    }
}
