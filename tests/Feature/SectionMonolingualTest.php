<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsResource;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A section field on a monolingual site is stored as a plain string — but a per-locale
 * array can still be in the column. Seeders ship every language, and a site that dropped
 * back to one locale keeps whatever it had.
 *
 * The frontend has always coped with that (HasSections resolves it), the editor did not:
 * with no locales configured a field binds to data.sections.0.head directly, so the input
 * received the array itself and showed "[object Object]" — and because the editor writes
 * its data back unchanged, the first keystroke replaced the whole array with one string.
 */
class SectionMonolingualTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // leap.locales stays at its default of null — that is the monolingual case. The
        // app locale is what decides which entry of a leftover per-locale array is shown,
        // so it is set here rather than left to the testbench default.
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

    public function test_a_per_locale_field_is_collapsed_to_the_active_locale(): void
    {
        $sections = $this->load([[
            '_name' => 'block',
            'active' => true,
            'head' => ['nl' => 'Kop', 'en' => 'Heading'],
            'body' => ['nl' => '<p>Tekst</p>', 'en' => '<p>Text</p>'],
        ]]);

        $this->assertSame('Kop', $sections[0]['head']);
        $this->assertSame('<p>Tekst</p>', $sections[0]['body']);
    }

    public function test_a_plain_string_passes_through_untouched(): void
    {
        $sections = $this->load([[
            '_name' => 'block',
            'head' => 'Al goed',
            'body' => '<p>Ook goed</p>',
        ]]);

        $this->assertSame('Al goed', $sections[0]['head']);
        $this->assertSame('<p>Ook goed</p>', $sections[0]['body']);
    }

    /**
     * A locale the site no longer serves is not what a visitor sees, so it is not what the
     * editor should show either. Falling back to the first entry beats showing nothing.
     */
    public function test_it_falls_back_to_the_first_entry_when_the_active_locale_is_missing(): void
    {
        $sections = $this->load([[
            '_name' => 'block',
            'head' => ['de' => 'Überschrift', 'fr' => 'Titre'],
        ]]);

        $this->assertSame('Überschrift', $sections[0]['head']);
    }

    /**
     * The collapse is limited to translatable sub-fields for exactly this reason: a json
     * attribute is an associative array too, and flattening it would throw the value away.
     */
    public function test_a_json_field_is_left_alone(): void
    {
        $meta = ['columns' => 3, 'nl' => 'not a translation'];

        $sections = $this->load([[
            '_name' => 'block',
            'head' => ['nl' => 'Kop'],
            'meta' => $meta,
        ]]);

        $this->assertSame($meta, $sections[0]['meta']);
    }

    /**
     * A list is not a translation set. Media pickers and any repeated value arrive as one,
     * and array_is_list() is what tells them apart from a per-locale map.
     */
    public function test_a_list_is_not_treated_as_a_translation_set(): void
    {
        $sections = $this->load([[
            '_name' => 'block',
            'head' => ['eerste', 'tweede'],
        ]]);

        $this->assertSame(['eerste', 'tweede'], $sections[0]['head']);
    }

    public function test_the_collapsed_section_title_still_reads_the_head(): void
    {
        $sections = $this->load([[
            '_name' => 'block',
            'head' => ['nl' => 'Kop', 'en' => 'Heading'],
        ]]);

        $this->assertSame('Kop', $sections[0]['_title']);
    }
}
