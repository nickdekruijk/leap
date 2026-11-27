<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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
use NickDeKruijk\Leap\Tests\Fixtures\SectionModalResource;
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
     * Insert sits with Duplicate and Delete and puts a section above its row, on every
     * row alike, the first one included. The end of the list has its own control under it.
     */
    public function test_every_row_offers_to_insert_a_section_above_it(): void
    {
        $editor = $this->editor([$this->block('Een'), $this->block('Twee'), $this->block('Drie')]);
        $html = $editor->html();

        $this->assertSame(3, substr_count($html, 'class="leap-button leap-section-insert"'));
        foreach ([0, 1, 2] as $index) {
            $this->assertStringContainsString("insertSection('sections', \$event.target.value, {$index}); ", $html);
        }
        $this->assertStringNotContainsString('leap-section-insert-top', $html);
        $this->assertStringNotContainsString(', true); ', $html);
        // At the end
        $editor->assertSeeHtml('class="leap-section-add"')
            ->assertSeeHtml("insertSection('sections', \$event.target.value); ");
    }

    public function test_insert_on_a_divider_adds_to_the_end_of_the_group_above_it(): void
    {
        $editor = $this->editor($this->page())->call('insertSection', 'sections', 'block', 4);

        $this->assertSame([0, 1, 2, 3, 6, 4, 5], $this->order($editor));
        $this->assertSame(1, $editor->instance()->sectionLayout('sections')[6]['group']);
    }

    public function test_insert_on_a_divider_after_a_folded_group_unfolds_it(): void
    {
        $editor = $this->editor($this->page())
            ->call('toggleSectionGroup', 'sections', 1)
            ->call('insertSection', 'sections', 'block', 4);

        $this->assertSame(1, $editor->instance()->sectionLayout('sections')[6]['group']);
        $this->assertFalse($editor->instance()->data['sections'][1]['_folded']);
    }

    public function test_rich_text_waits_for_a_click_in_the_list_and_opens_straight_away_in_the_modal(): void
    {
        $this->editor([['_closed' => false] + $this->block('Een')])->assertSeeHtml('lazy: true')->assertDontSeeHtml('lazy: false');

        $this->modal([$this->block('Een')])
            ->call('editSection', 'sections', 0)
            ->assertSeeHtml('lazy: false')
            ->assertDontSeeHtml('lazy: true')
            ->assertSeeHtml('data-leap-richtext="eager"');
    }

    /**
     * The dialog shows itself once its rich text has started, so it does not grow and
     * jump while it is looked at. It counts the editors that start right away, and gives
     * up waiting after a second.
     */
    public function test_the_modal_waits_for_its_rich_text_before_it_shows(): void
    {
        $this->modal([$this->block('Een')])
            ->call('editSection', 'sections', 0)
            ->assertSeeHtml("querySelectorAll('[data-leap-richtext=eager]')")
            ->assertSeeHtml('x-on:leap-richtext-ready="if (--waiting <= 0) ready = true"')
            ->assertSeeHtml('setTimeout(() => ready = true, 1000)');
    }

    public function test_an_empty_list_can_still_be_added_to(): void
    {
        $this->editor([])
            ->assertSeeHtml('class="leap-section-add"')
            ->assertDontSeeHtml('leap-section-insert"');
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

    // ---- Editing in a modal -----------------------------------------------------------

    private function modal(array $sections): Testable
    {
        config(['leap.sections.modal' => true]);

        return $this->editor($sections);
    }

    public function test_in_modal_mode_the_list_draws_no_fields_and_a_click_opens_the_modal(): void
    {
        $editor = $this->modal([['_closed' => false] + $this->block('Een'), $this->block('Twee')]);

        $editor->assertDontSeeHtml('data.sections.0.head.nl')
            ->assertSeeHtml("editSection('sections', 0)")
            ->assertDontSeeHtml("toggleSection('sections', 0)")
            ->assertDontSeeHtml('toggleAllSections')
            ->assertDontSeeHtml('class="leap-modal leap-section-modal"');

        $editor->call('editSection', 'sections', 1);

        $this->assertSame('sections', $editor->instance()->sectionModal['attribute']);
        $this->assertSame(1, $editor->instance()->sectionModal['index']);
        $editor->assertSeeHtml('class="leap-modal leap-section-modal"')
            ->assertSeeHtml('data.sections.1.head.nl')
            ->assertDontSeeHtml('data.sections.0.head.nl');
    }

    /**
     * Without modal mode a row opens in the list, and the Edit button of a closed one
     * opens the modal. A section open in the list is closed there meanwhile, or its
     * fields would be on the page twice, and opens again when the modal closes.
     */
    public function test_without_modal_mode_the_edit_button_opens_the_modal(): void
    {
        $editor = $this->editor([['_closed' => false] + $this->block('Een'), ['_closed' => true] + $this->block('Twee')]);

        // Only the closed one: an open section is being edited in the list already
        $editor->assertSeeHtml("toggleSection('sections', 0)")
            ->assertDontSeeHtml("editSection('sections', 0)")
            ->assertSeeHtml("editSection('sections', 1)");
        $this->assertSame(1, substr_count($editor->html(), 'leap-section-edit'));
        // Open in the list: its rich text is there once
        $this->assertSame(1, substr_count($editor->html(), 'leap-rt-data-sections-0-body-nl'));

        $editor->call('editSection', 'sections', 0)
            ->assertSeeHtml('class="leap-modal leap-section-modal"');
        // Once, in the modal, and not in the list as well
        $this->assertSame(1, substr_count($editor->html(), 'leap-rt-data-sections-0-body-nl'));
        $this->assertFalse($editor->instance()->data['sections'][0]['_closed']);

        $editor->call('closeSection')->assertDontSeeHtml('class="leap-modal leap-section-modal"');
        $this->assertSame(1, substr_count($editor->html(), 'leap-rt-data-sections-0-body-nl'));
    }

    public function test_in_modal_mode_there_is_no_edit_button(): void
    {
        $this->modal([$this->block('Een')])->assertDontSeeHtml('leap-section-edit');
    }

    public function test_an_unknown_section_opens_no_modal(): void
    {
        $editor = $this->modal([$this->block('Een')])->call('editSection', 'sections', 7);

        $this->assertNull($editor->instance()->sectionModal);
    }

    public function test_done_keeps_what_was_changed_in_the_form(): void
    {
        $editor = $this->modal([$this->block('Een')])
            ->call('editSection', 'sections', 0)
            ->set('data.sections.0.head.nl', 'Andere kop')
            ->call('closeSection');

        $this->assertNull($editor->instance()->sectionModal);
        $this->assertSame('Andere kop', $editor->instance()->data['sections'][0]['head']['nl']);
        $this->assertTrue($editor->instance()->isDirty());
    }

    public function test_cancel_puts_the_section_and_its_media_back(): void
    {
        Storage::disk('public')->put('photo.jpg', (string) Media::imageManager()->createImage(20, 10)->encodeUsingFileExtension('jpg'));
        Storage::disk('public')->put('other.jpg', (string) Media::imageManager()->createImage(20, 10)->encodeUsingFileExtension('jpg'));
        $media = Media::forFile('photo.jpg', 'public');
        $other = Media::forFile('other.jpg', 'public');

        config(['leap.sections.modal' => true]);
        $record = $this->record([$this->block('Een'), ['_name' => 'image', 'caption' => 'Het gebouw']]);
        Mediable::create(['media_id' => $media->id, 'mediable_type' => SectionsModel::class, 'mediable_id' => $record->id, 'mediable_attribute' => 'sections.1.image', 'sort' => 0]);

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id)
            ->call('editSection', 'sections', 1)
            ->set('data.sections.1.caption', 'Iets anders')
            ->call('unselectMedia', 'sections.1.image', 0)
            ->call('selectMediaFiles', 'sections.1.image', ['other.jpg'])
            ->call('cancelSection');

        $data = $editor->instance()->data;
        $this->assertNull($editor->instance()->sectionModal);
        $this->assertSame('Het gebouw', $data['sections'][1]['caption']);
        $this->assertSame([$media->id], $data['sections.1.image']);
        $this->assertNotSame($other->id, $media->id);
        $this->assertFalse($editor->instance()->isDirty());
    }

    public function test_a_section_added_in_modal_mode_opens_in_the_modal_and_cancel_takes_it_away(): void
    {
        $editor = $this->modal([$this->block('Een')])->call('insertSection', 'sections', 'text', 0, true);

        $this->assertSame(1, $editor->instance()->sectionModal['index']);
        $this->assertTrue($editor->instance()->sectionModal['new']);
        $editor->assertSeeHtml('data.sections.1.body.nl');

        $editor->call('cancelSection');

        $this->assertSame([0], array_keys($editor->instance()->data['sections']));
        $this->assertNull($editor->instance()->sectionModal);
    }

    public function test_done_and_save_stores_the_record_and_closes_the_modal(): void
    {
        $editor = $this->modal([$this->block('Een')]);
        $id = $editor->instance()->editing;

        $editor->call('editSection', 'sections', 0)
            ->set('data.sections.0.head.nl', 'Opgeslagen kop')
            ->call('saveSection');

        $this->assertSame('Opgeslagen kop', SectionsModel::find($id)->sections[0]['head']['nl']);
        $this->assertNull($editor->instance()->sectionModal);
        $this->assertFalse($editor->instance()->isDirty());
        $editor->assertDispatchedTo(Toasts::class, 'toast');
    }

    public function test_opening_a_record_closes_the_modal(): void
    {
        $editor = $this->modal([$this->block('Een')])->call('editSection', 'sections', 0);

        $editor->call('openEditor', $this->record([$this->block('Ander record')])->id);

        $this->assertNull($editor->instance()->sectionModal);
    }

    public function test_the_browser_cannot_point_the_modal_anywhere(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->modal([$this->block('Een')])->set('sectionModal', ['attribute' => 'title', 'index' => 0, 'new' => false, 'section' => [], 'media' => []]);
    }

    public function test_an_attribute_says_for_itself_whether_its_sections_open_in_a_modal(): void
    {
        $this->app['config']->set('leap.default_modules', [Dashboard::class, SectionModalResource::class, FileManager::class]);
        Leap::context()->setModule(SectionModalResource::class);
        Leap::context()->setPermissions([SectionModalResource::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true]]);
        $editor = new Editor;

        config(['leap.sections.modal' => false]);
        SectionModalResource::$modal = true;
        $this->assertTrue($editor->sectionsInModal('sections'));

        config(['leap.sections.modal' => true]);
        SectionModalResource::$modal = false;
        $this->assertFalse($editor->sectionsInModal('sections'));

        SectionModalResource::$modal = true;
        $this->assertFalse($editor->sectionsInModal('title'));
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
