<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Livewire\Toasts;
use NickDeKruijk\Leap\Models\Log;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Mediable;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\SectionListResource;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsModel;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The list of sections in the editor, as a list: what a collapsed section says about
 * itself, how sections are added, copied, grouped and moved, and that opening or closing
 * one is remembered without counting as a change to the record.
 */
class EditorSectionListTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.default_modules', [
            Dashboard::class,
            SectionListResource::class,
            // A thumbnail links to the file the way the media field does
            FileManager::class,
        ]);
        $app['config']->set('leap.locales', ['nl' => 'Nederlands', 'en' => 'English']);
        $app['config']->set('app.locale', 'nl');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['leap.filemanager.disk' => 'public']);

        Schema::create('sections_models', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('sections')->nullable();
            $table->timestamps();
        });

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        Leap::context()->setModule(SectionListResource::class);
        Leap::context()->setPermissions([
            SectionListResource::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function record(array $sections): SectionsModel
    {
        foreach ($sections as $sort => $section) {
            // _view as the editor stores it on every save, so the first save in a test
            // is not the one that adds it
            $sections[$sort] += ['_sort' => $sort, '_view' => 'sections.'.$section['_name']];
        }

        $record = SectionsModel::create(['title' => ['nl' => 'Over ons'], 'sections' => $sections]);

        // An age, so a test can tell whether saving touched it
        SectionsModel::whereKey($record->id)->update(['updated_at' => '2020-01-01 12:00:00']);

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private function block(string $head, bool $active = true): array
    {
        return ['_name' => 'block', 'active' => $active, 'head' => ['nl' => $head, 'en' => $head.' (en)'], 'body' => ['nl' => '', 'en' => '']];
    }

    /**
     * @return array<string, mixed>
     */
    private function tab(string $head): array
    {
        return ['_name' => 'tab', 'head' => $head];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function editor(array $sections): Testable
    {
        return Livewire::test(Editor::class)->call('openEditor', $this->record($sections)->id);
    }

    /**
     * The section keys in the order the list shows them.
     *
     * @return list<int|string>
     */
    private function order(Testable $editor): array
    {
        return array_keys(collect($editor->instance()->data['sections'])->sortBy('_sort')->all());
    }

    /**
     * An intro, a tab with two blocks, and a tab with one.
     *
     * @return array<int, array<string, mixed>>
     */
    private function page(): array
    {
        return [
            0 => $this->block('Intro'),
            1 => $this->tab('Aanleiding'),
            2 => $this->block('Eerste'),
            3 => $this->block('Tweede'),
            4 => $this->tab('Programma'),
            5 => $this->block('Derde'),
        ];
    }

    // ---- View state is not content ----------------------------------------------------

    public function test_opening_or_closing_a_section_is_not_an_unsaved_change(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->tab('Tab')]);

        $editor->call('toggleSection', 'sections', 0)->call('toggleSectionGroup', 'sections', 1);

        $this->assertTrue($editor->instance()->data['sections'][0]['_closed']);
        $this->assertTrue($editor->instance()->data['sections'][1]['_folded']);
        $this->assertFalse($editor->instance()->isDirty());
    }

    /**
     * _title is rebuilt in the language the editor shows, and it is stored. Looking at
     * the English tab used to be enough to be asked about unsaved changes.
     */
    public function test_switching_the_language_tab_is_not_an_unsaved_change(): void
    {
        $editor = $this->editor([$this->block('Kop')])->set('activeLocale', 'en');

        $this->assertSame('Kop (en)', $editor->instance()->data['sections'][0]['_title']);
        $this->assertFalse($editor->instance()->isDirty());
    }

    public function test_a_change_to_what_a_section_holds_still_is(): void
    {
        $editor = $this->editor([$this->block('Kop')])->set('data.sections.0.head.nl', 'Andere kop');

        $this->assertTrue($editor->instance()->isDirty());
    }

    public function test_saving_only_a_closed_section_stores_it_without_updating_the_record(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee')]);
        $id = $editor->instance()->editing;

        // An observer that clears a cache or pings a search index has nothing to do
        $events = 0;
        SectionsModel::saved(function () use (&$events): void {
            $events++;
        });

        $editor->call('toggleSection', 'sections', 0)->call('save');

        $stored = SectionsModel::find($id);
        $this->assertTrue($stored->sections[0]['_closed']);
        $this->assertSame('2020-01-01 12:00:00', $stored->updated_at->toDateTimeString());
        $this->assertSame(0, $events);
        $this->assertSame(0, Log::where('action', 'update')->count());

        $editor->assertDispatchedTo(Toasts::class, 'toast-alert');
        $editor->assertNotDispatched('toast');
    }

    public function test_a_real_change_is_saved_as_before_and_takes_the_view_state_along(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee')]);
        $id = $editor->instance()->editing;

        $editor->call('toggleSection', 'sections', 1)
            ->set('data.sections.0.head.nl', 'Andere kop')
            ->call('save');

        $stored = SectionsModel::find($id);
        $this->assertSame('Andere kop', $stored->sections[0]['head']['nl']);
        $this->assertTrue($stored->sections[1]['_closed']);
        $this->assertNotSame('2020-01-01 12:00:00', $stored->updated_at->toDateTimeString());
        $this->assertSame(1, Log::where('action', 'update')->count());

        $editor->assertDispatchedTo(Toasts::class, 'toast');
    }

    // ---- Collapsed by default ---------------------------------------------------------

    public function test_a_long_page_opens_with_its_sections_closed(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee'), $this->block('Drie')]);

        $this->assertSame([true, true, true], array_column($editor->instance()->data['sections'], '_closed'));
        $this->assertFalse($editor->instance()->isDirty());
    }

    public function test_a_page_of_one_or_two_sections_opens_with_them_open(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee')]);

        $this->assertSame([], array_column($editor->instance()->data['sections'], '_closed'));
    }

    public function test_a_section_somebody_opened_stays_open(): void
    {
        $editor = $this->editor([$this->block('Een'), ['_closed' => false] + $this->block('Twee'), $this->block('Drie')]);

        $this->assertSame([true, false, true], array_column($editor->instance()->data['sections'], '_closed'));
    }

    public function test_the_config_switches_collapsing_off(): void
    {
        config(['leap.sections.collapsed' => false]);

        $editor = $this->editor([$this->block('Een'), $this->block('Twee'), $this->block('Drie')]);

        $this->assertSame([], array_column($editor->instance()->data['sections'], '_closed'));
    }

    // ---- Adding, inserting, copying ---------------------------------------------------

    /**
     * And the two that were open stay open: with a third section the page counts as long
     * the next time it is opened, so what was on screen is written down first.
     */
    public function test_a_new_section_is_open_and_leaves_the_others_as_they_were(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee')])->set('data.sections:add', 'block');

        $sections = $editor->instance()->data['sections'];

        $this->assertCount(3, $sections);
        $this->assertSame([false, false, false], array_column($sections, '_closed'));
        $this->assertSame([0, 1, 2], $this->order($editor));
        $this->assertTrue($sections[2]['active']);
        $editor->assertSet('data.sections:add', null);
    }

    public function test_a_section_can_be_inserted_in_front_of_another(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee'), $this->block('Drie')])
            ->call('insertSection', 'sections', 'text', 1);

        $this->assertSame([0, 3, 1, 2], $this->order($editor));
        $this->assertSame('text', $editor->instance()->data['sections'][3]['_name']);
        $this->assertSame([0, 1, 2, 3], collect($editor->instance()->data['sections'])->sortBy('_sort')->pluck('_sort')->all());
    }

    /**
     * What the "+" under a row asks for.
     */
    public function test_a_section_can_be_inserted_after_another(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee'), $this->block('Drie')])
            ->call('insertSection', 'sections', 'text', 0, true);

        $this->assertSame([0, 3, 1, 2], $this->order($editor));

        // After the last one is the end of the list
        $editor->call('insertSection', 'sections', 'text', 2, true);

        $this->assertSame([0, 3, 1, 2, 4], $this->order($editor));
    }

    public function test_after_the_last_section_of_a_group_is_still_inside_that_group(): void
    {
        $editor = $this->editor($this->page())->call('insertSection', 'sections', 'block', 3, true);

        $this->assertSame([0, 1, 2, 3, 6, 4, 5], $this->order($editor));
        $this->assertSame(1, $editor->instance()->sectionLayout('sections')[6]['group']);
    }

    public function test_after_a_divider_is_the_first_place_in_its_group(): void
    {
        $editor = $this->editor($this->page())->call('insertSection', 'sections', 'block', 1, true);

        $this->assertSame([0, 1, 6, 2, 3, 4, 5], $this->order($editor));
        $this->assertSame(1, $editor->instance()->sectionLayout('sections')[6]['group']);
    }

    /**
     * A folded group is one row, and the section added under it would be out of sight.
     */
    public function test_after_a_folded_divider_unfolds_its_group(): void
    {
        $editor = $this->editor($this->page())
            ->call('toggleSectionGroup', 'sections', 1)
            ->call('insertSection', 'sections', 'block', 1, true);

        $this->assertSame([0, 1, 6, 2, 3, 4, 5], $this->order($editor));
        $this->assertFalse($editor->instance()->data['sections'][1]['_folded']);
    }

    /**
     * One "+" under every row but the last, whose place is taken by the control under
     * the list, and one in front of the first row, which "under" cannot reach.
     */
    public function test_every_row_offers_to_add_a_section_below_it(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee'), $this->block('Drie')]);

        $editor->assertSeeHtml("insertSection('sections', \$event.target.value, 0, true)")
            ->assertSeeHtml("insertSection('sections', \$event.target.value, 1, true)")
            ->assertDontSeeHtml("insertSection('sections', \$event.target.value, 2, true)")
            // In front of the first row only
            ->assertSeeHtml("insertSection('sections', \$event.target.value, 0); ")
            ->assertDontSeeHtml("insertSection('sections', \$event.target.value, 1); ")
            // At the end
            ->assertSeeHtml('class="leap-section-add"')
            ->assertSeeHtml("insertSection('sections', \$event.target.value); ");
    }

    public function test_an_empty_list_can_still_be_added_to(): void
    {
        $this->editor([])
            ->assertSeeHtml('class="leap-section-add"')
            ->assertDontSeeHtml('leap-section-insert');
    }

    public function test_the_control_under_the_list_steps_in_when_the_list_ends_in_a_group(): void
    {
        $this->editor($this->page())->assertSeeHtml('class="leap-section-add leap-section-add-grouped"');
    }

    public function test_inserting_an_unknown_section_adds_nothing(): void
    {
        $editor = $this->editor([$this->block('Een')])->call('insertSection', 'sections', 'removed-section', 0);

        $this->assertCount(1, $editor->instance()->data['sections']);
    }

    public function test_a_section_is_duplicated_right_below_itself_with_its_media(): void
    {
        Storage::disk('public')->put('photo.jpg', (string) Media::imageManager()->createImage(20, 10)->encodeUsingFileExtension('jpg'));
        $media = Media::forFile('photo.jpg', 'public');

        $record = $this->record([
            $this->block('Een'),
            ['_name' => 'image', 'caption' => 'Het gebouw', '_closed' => true],
            $this->block('Drie'),
        ]);
        Mediable::create(['media_id' => $media->id, 'mediable_type' => SectionsModel::class, 'mediable_id' => $record->id, 'mediable_attribute' => 'sections.1.image', 'sort' => 0]);

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id)->call('duplicateSection', 'sections', 1);
        $data = $editor->instance()->data;

        $this->assertSame([0, 1, 3, 2], $this->order($editor));
        $this->assertSame('Het gebouw', $data['sections'][3]['caption']);
        $this->assertFalse($data['sections'][3]['_closed']);
        $this->assertSame([$media->id], $data['sections.3.image']);
        $this->assertTrue($editor->instance()->isDirty());

        $editor->call('save');

        $this->assertSame(
            ['sections.1.image', 'sections.3.image'],
            Mediable::where('mediable_id', $record->id)->orderBy('mediable_attribute')->pluck('mediable_attribute')->all(),
        );
    }

    // ---- What a collapsed section says ------------------------------------------------

    private function summary(array $content, string $section, string $locale = 'nl'): string
    {
        $editor = new Editor;
        $editor->activeLocale = $locale;

        $definition = collect((new SectionListResource)->attributes())->firstWhere('name', 'sections')->sections;

        return $editor->sectionSummary($content, collect($definition)->firstWhere('name', $section));
    }

    public function test_the_summary_is_the_section_title_when_there_is_one(): void
    {
        $this->assertSame('Kop', $this->summary(['_title' => 'Kop', 'body' => ['nl' => '<p>Tekst</p>']], 'block'));
    }

    public function test_the_summary_falls_back_to_the_first_text_without_its_markup(): void
    {
        $content = ['layout' => 'left', 'body' => ['nl' => '<h2>Kop</h2><p>Tekst &amp;&nbsp;meer</p>', 'en' => '<p>Text</p>']];

        $this->assertSame('Kop Tekst & meer', $this->summary($content, 'text'));
        $this->assertSame('Text', $this->summary($content, 'text', 'en'));
    }

    public function test_a_select_or_a_switch_is_not_a_summary(): void
    {
        $this->assertSame('', $this->summary(['active' => true, 'layout' => 'left', 'body' => ['nl' => '', 'en' => '']], 'text'));
    }

    public function test_the_summary_is_one_line_of_limited_length(): void
    {
        $summary = $this->summary(['body' => ['nl' => '<p>'.str_repeat('woord ', 40).'</p>']], 'text');

        $this->assertSame(163, mb_strlen($summary));
        $this->assertStringEndsWith('...', $summary);
    }

    public function test_a_closed_row_shows_its_summary_and_a_switched_off_one_is_marked(): void
    {
        $editor = $this->editor([
            ['_name' => 'text', 'active' => true, 'body' => ['nl' => '<p>Eerste alinea</p>', 'en' => '']],
            $this->block('Uitgezet', false),
            $this->block('Drie'),
        ]);

        $editor->assertSee('Eerste alinea')
            ->assertSeeHtml('leap-editor-section-closed')
            ->assertSeeHtml('leap-editor-section-inactive')
            // Closed, so none of the fields are drawn
            ->assertDontSeeHtml('data.sections.0.body');

        // Open, and the summary makes way for the fields
        $editor->call('toggleSection', 'sections', 0)
            ->assertSeeHtml('data.sections.0.body')
            ->assertDontSeeHtml('<span class="leap-label-sub">Eerste alinea</span>');
    }

    public function test_a_closed_row_shows_the_first_image_of_its_section(): void
    {
        Storage::disk('public')->put('photo.jpg', (string) Media::imageManager()->createImage(20, 10)->encodeUsingFileExtension('jpg'));
        $media = Media::forFile('photo.jpg', 'public');

        $record = $this->record([$this->block('Een'), ['_name' => 'image', 'caption' => 'Het gebouw'], $this->block('Drie')]);
        Mediable::create(['media_id' => $media->id, 'mediable_type' => SectionsModel::class, 'mediable_id' => $record->id, 'mediable_attribute' => 'sections.1.image', 'sort' => 0]);

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);

        $this->assertSame([1], array_keys($editor->instance()->sectionThumbnails('sections')));
        $editor->assertSeeHtml('class="leap-section-thumbnail"');
    }

    // ---- Dividers and their groups ----------------------------------------------------

    public function test_a_section_is_not_a_divider_unless_it_says_so(): void
    {
        $this->assertFalse(Section::make('block')->divider);
        $this->assertTrue(Section::make('tab')->divider()->divider);
    }

    public function test_a_divider_groups_the_sections_up_to_the_next_one(): void
    {
        $layout = $this->editor($this->page())->instance()->sectionLayout('sections');

        $this->assertSame([0, 1, 2, 3, 4, 5], array_keys($layout));
        $this->assertSame([null, null, 1, 1, null, 4], array_column($layout, 'group'));
        $this->assertSame([false, true, false, false, true, false], array_column($layout, 'divider'));
        $this->assertSame(2, $layout[1]['members']);
        $this->assertSame(1, $layout[4]['members']);
    }

    public function test_folding_a_group_takes_its_sections_off_the_list(): void
    {
        $editor = $this->editor($this->page())->call('toggleSectionGroup', 'sections', 1);

        $layout = $editor->instance()->sectionLayout('sections');
        $this->assertSame([false, false, true, true, false, false], array_column($layout, 'hidden'));

        $editor->assertDontSee('Eerste')
            ->assertDontSee('Tweede')
            ->assertSee('Derde')
            ->assertSeeHtml('<span class="leap-section-count">2</span>');
    }

    public function test_opening_everything_unfolds_the_groups_too(): void
    {
        $editor = $this->editor($this->page())
            ->call('toggleSectionGroup', 'sections', 1)
            ->call('toggleAllSections', 'sections', false);

        $this->assertFalse($editor->instance()->data['sections'][1]['_folded']);
        $this->assertFalse($editor->instance()->hasClosedSection('sections'));
    }

    // ---- Moving -----------------------------------------------------------------------

    public function test_a_section_moves_to_the_position_it_was_dropped_at(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee'), $this->block('Drie')])
            ->call('sortSection', 'sections', 2, 0);

        $this->assertSame([2, 0, 1], $this->order($editor));
        $this->assertSame([0, 1, 2], collect($editor->instance()->data['sections'])->sortBy('_sort')->pluck('_sort')->all());
    }

    public function test_an_open_divider_moves_alone(): void
    {
        // Dropped in front of the second tab: the two blocks stay where they were, and
        // now come before the divider that used to head them.
        $editor = $this->editor($this->page())->call('sortSection', 'sections', 1, 3);

        $this->assertSame([0, 2, 3, 1, 4, 5], $this->order($editor));
    }

    public function test_a_folded_divider_takes_its_group_along(): void
    {
        // The list shows four rows now: intro, the folded tab, the second tab, its block.
        // Dropped at the very end.
        $editor = $this->editor($this->page())
            ->call('toggleSectionGroup', 'sections', 1)
            ->call('sortSection', 'sections', 1, 3);

        $this->assertSame([0, 4, 5, 1, 2, 3], $this->order($editor));
        $this->assertTrue($editor->instance()->data['sections'][1]['_folded']);
    }

    public function test_positions_are_counted_over_the_rows_on_screen(): void
    {
        // Rows: intro, folded tab, second tab, its block. The last block is dropped in
        // front of the second tab, which puts it behind the two hidden sections.
        $editor = $this->editor($this->page())
            ->call('toggleSectionGroup', 'sections', 1)
            ->call('sortSection', 'sections', 5, 2);

        $this->assertSame([0, 1, 2, 3, 5, 4], $this->order($editor));
    }

    public function test_a_section_dropped_into_a_folded_group_unfolds_it(): void
    {
        $editor = $this->editor($this->page())
            ->call('toggleSectionGroup', 'sections', 1)
            ->call('sortSection', 'sections', 5, 2);

        $this->assertFalse($editor->instance()->data['sections'][1]['_folded']);
    }

    public function test_a_section_added_below_a_folded_group_unfolds_it(): void
    {
        $editor = $this->editor($this->page())
            ->call('toggleSectionGroup', 'sections', 4)
            ->set('data.sections:add', 'block');

        $this->assertFalse($editor->instance()->data['sections'][4]['_folded']);
        $this->assertSame([0, 1, 2, 3, 4, 5, 6], $this->order($editor));
    }

    // ---- The site ---------------------------------------------------------------------

    public function test_the_site_renders_the_same_sections_whatever_the_editor_folded(): void
    {
        $plain = $this->record($this->page());
        $folded = $this->record(array_map(fn (array $section): array => $section + ['_closed' => true, '_folded' => true], $this->page()));

        $names = fn (SectionsModel $record): array => $record->sections()->pluck('_name')->values()->all();

        $this->assertSame($names($plain), $names($folded));
        $this->assertCount(6, $folded->sections());
    }
}
