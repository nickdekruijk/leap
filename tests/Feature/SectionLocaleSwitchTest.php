<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsResource;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Switching a site between one language and several has to work in both directions,
 * without a migration and without anyone losing text.
 *
 * A section field is stored as a plain string while the site is monolingual and as a
 * per-locale array once leap.locales is filled in. Neither switch rewrites the column:
 * the editor converts what it finds when a page is opened, and the frontend renders both
 * shapes either way. A page nobody edits simply keeps the shape it has.
 *
 * The monolingual direction is covered in SectionMonolingualTest; this is the other one.
 */
class SectionLocaleSwitchTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The site just became multilingual; nl is the default locale.
        $app['config']->set('leap.locales', ['nl' => 'Nederlands', 'en' => 'English']);
        $app['config']->set('app.locale', 'nl');
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function load(array $sections): array
    {
        Leap::context()->setModule(SectionsResource::class);

        $editor = new Editor;
        $editor->data = ['sections' => $sections];
        $editor->checkSectionValues();

        return $editor->data['sections'];
    }

    public function test_a_string_from_the_monolingual_era_is_kept_as_the_default_locale(): void
    {
        $sections = $this->load([[
            '_name' => 'block',
            'head' => 'Laravel development',
            'body' => '<p>Van datamodel tot deploy.</p>',
        ]]);

        // Not thrown away and not silently overwritten by the first keystroke: the text
        // that was there is now the Dutch translation, and English is still to be written.
        $this->assertSame(['nl' => 'Laravel development'], $sections[0]['head']);
        $this->assertSame(['nl' => '<p>Van datamodel tot deploy.</p>'], $sections[0]['body']);
    }

    public function test_an_already_translated_field_is_left_alone(): void
    {
        $translations = ['nl' => 'Kop', 'en' => 'Heading'];

        $sections = $this->load([[
            '_name' => 'block',
            'head' => $translations,
        ]]);

        $this->assertSame($translations, $sections[0]['head']);
    }

    public function test_an_empty_field_is_not_wrapped(): void
    {
        $sections = $this->load([[
            '_name' => 'block',
            'head' => 'Kop',
            'body' => '   ',
        ]]);

        // Wrapping whitespace would create a translation that says nothing, and the field
        // would then look filled in for a language nobody has written yet.
        $this->assertSame('   ', $sections[0]['body']);
    }

    /**
     * Only fields the resource marks translatable. A select or a json field holds the same
     * value in every language and has no business being split per locale.
     */
    public function test_a_shared_field_stays_shared(): void
    {
        $sections = $this->load([[
            '_name' => 'block',
            'head' => 'Kop',
            'layout' => 'left',
            'meta' => ['columns' => 3],
        ]]);

        $this->assertSame('left', $sections[0]['layout']);
        $this->assertSame(['columns' => 3], $sections[0]['meta']);
    }
}
