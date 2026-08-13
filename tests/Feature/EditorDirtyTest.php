<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Livewire\Toasts;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\PreviewableModel;
use NickDeKruijk\Leap\Tests\Fixtures\PreviewableResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Knowing whether an open editor has unsaved work, so clicking another row, starting a
 * new record or leaving the page can ask first instead of dropping it silently.
 *
 * Answered on the server because most of what changes an editor never surfaces as an
 * input event in the browser: media picked, a section added, a pivot toggled, an AI
 * translation filled in. The browser only adds the typing that has not been sent yet.
 */
class EditorDirtyTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.default_modules', [
            Dashboard::class,
            PreviewableResource::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('previewfixture', __DIR__.'/../Fixtures/views');

        Schema::create('previewable_models', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('active')->default(true);
            $table->dateTime('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        Leap::context()->setModule(PreviewableResource::class);
        Leap::context()->setPermissions([
            PreviewableResource::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    private function record(string $title = 'Over ons'): PreviewableModel
    {
        return PreviewableModel::create(['title' => $title, 'body' => 'Saved body', 'active' => true]);
    }

    /**
     * The verdict travels in an Alpine store, and it has to stay there.
     *
     * It was a variable declared on <main>, which is a Livewire component root. Livewire
     * owns the Alpine scope of its root and routes assignments through its own property
     * proxy, so "editorDirty = …" threw "path.replace is not a function" — and a handler
     * that throws leaves the value in a state the browser reads as truthy, which is how
     * an untouched editor warned about unsaved changes on every reload.
     */
    public function test_the_dirty_flag_lives_in_a_store_and_not_on_a_livewire_root(): void
    {
        $response = $this->get(route('leap.module.previews'))->assertOk();

        $response->assertSee("Alpine.store('leapEditor'", false);
        $response->assertSee('get dirty()', false);

        // Nothing of ours added to the scope Livewire owns
        $response->assertSee('x-data="{ selectedRow: $wire.entangle(\'selectedRow\') }"', false);
        $response->assertDontSee('editorDirty', false);

        // And nothing reads one of our own properties off $wire: what comes back is the
        // property while Livewire has it and a callable when it does not, which is neither
        // true nor false. The server's answer arrives through the commit hook instead.
        $response->assertSee('$store.leapEditor?.dirty === true', false);
        $response->assertSee("editorWire?.get?.('dirty')", false);
        $response->assertDontSee('$wire.dirty', false);
        $response->assertDontSee('if ($store.leapEditor.dirty)', false);

        // Every way out that can wait for an answer asks the server for one, so typing that
        // was undone again does not raise a question about changes that no longer exist —
        // and each acts inside .then(), never on a bare return, so a promise nobody waited
        // for cannot let a click through and drop the changes.
        $response->assertSee('$store.leapEditor.confirmLeave(', false);
        $response->assertSee('async confirmLeave(message)', false);
        $response->assertSee('.then(ok =>', false);
        $response->assertDontSee('!await $store.leapEditor.confirmLeave', false);
    }

    /**
     * The question the panel asks before it warns. A method, not the property: the typing
     * only reaches the server with a request, and asking is that request.
     */
    public function test_the_editor_answers_whether_it_is_still_dirty(): void
    {
        $record = $this->record();

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);
        $this->assertFalse($editor->instance()->stillDirty());

        $editor->set('data.body', 'Half a sentence');
        $this->assertTrue($editor->instance()->stillDirty());

        // Typed and undone again: nothing differs, so nobody should be asked anything.
        $editor->set('data.body', 'Saved body');
        $this->assertFalse($editor->instance()->stillDirty());
    }

    /**
     * With nothing open there is nothing to lose, whatever is left in $data. Said here so
     * closing an editor settles the question on the next round trip.
     */
    public function test_a_closed_editor_is_never_dirty(): void
    {
        $record = $this->record();

        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.body', 'Half a sentence')
            ->call('close');

        $this->assertFalse($editor->instance()->isDirty());
        $editor->assertSet('dirty', false);
    }

    public function test_a_freshly_opened_editor_is_not_dirty(): void
    {
        $record = $this->record();

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);

        $this->assertFalse($editor->instance()->isDirty());
        $editor->assertSet('dirty', false);
    }

    /**
     * The canary for the fingerprint: a round trip that changes nothing must not start
     * reporting changes, or every close would ask a pointless question.
     */
    public function test_an_unrelated_round_trip_leaves_it_clean(): void
    {
        $record = $this->record();

        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->call('$refresh');

        $this->assertFalse($editor->instance()->isDirty());
    }

    public function test_typing_makes_it_dirty(): void
    {
        $record = $this->record();

        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.body', 'Half a sentence');

        $this->assertTrue($editor->instance()->isDirty());
        $editor->assertSet('dirty', true);
    }

    public function test_saving_makes_it_clean_again(): void
    {
        $record = $this->record();

        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.body', 'Rewritten')
            ->call('save');

        $this->assertFalse($editor->instance()->isDirty());
        $this->assertSame('Rewritten', $record->fresh()->body);
    }

    /**
     * The case the browser cannot see: no field was typed in, a Livewire action moved
     * the data. A dirty check that only listened to input events would miss it.
     */
    public function test_a_change_made_by_a_livewire_action_counts(): void
    {
        $record = $this->record();

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);

        $editor->instance()->data['images'] = [12];

        $this->assertTrue($editor->instance()->isDirty());
    }

    public function test_opening_another_record_starts_clean(): void
    {
        $first = $this->record('First');
        $second = $this->record('Second');

        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $first->id)
            ->set('data.body', 'Typing in the first')
            ->call('openEditor', $second->id);

        $this->assertFalse($editor->instance()->isDirty());
    }

    /**
     * A round trip is not a save. Switching a language tab sends the typing along, so the
     * browser's "keys were pressed" is settled by it — but what was typed is still
     * unsaved, and the answer has to stay yes.
     */
    public function test_a_round_trip_does_not_clear_unsaved_changes(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $record = PreviewableModel::create(['title' => ['nl' => 'Over ons', 'en' => 'About us'], 'active' => true]);

        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.title.nl', 'Over ons, herschreven')
            ->set('activeLocale', 'en');

        $this->assertTrue($editor->instance()->stillDirty());
        $editor->assertSet('dirty', true);

        // And the record itself is untouched: a locale switch saves nothing.
        $this->assertSame('Over ons', $record->fresh()->getTranslation('title', 'nl'));
    }

    /**
     * Multilingual: a change in a language tab you are not looking at is still a change.
     */
    public function test_editing_another_locale_counts(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $record = PreviewableModel::create(['title' => ['nl' => 'Over ons', 'en' => 'About us'], 'active' => true]);

        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.title.en', 'About us, rewritten');

        $this->assertSame('nl', $editor->instance()->activeLocale);
        $this->assertTrue($editor->instance()->isDirty());
    }

    /**
     * Regression guard: save() decides on the model's own dirty state, not on this, so
     * an untouched editor still says there is nothing to save.
     */
    public function test_saving_an_untouched_editor_still_reports_no_changes(): void
    {
        $record = $this->record();

        Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->call('save')
            ->assertDispatchedTo(Toasts::class, 'toast-alert');
    }
}
