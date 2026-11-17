<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsModel;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A translatable rich text field in a section binds to data.sections.N.body.en. When the
 * editor filled a missing value with an empty string, the browser could not write the
 * .en key into it: JavaScript ignores a property set on a string. The text typed in a
 * new FAQ or call to action never reached the server, while the heading, a plain input
 * that starts out missing, saved fine. Only the heading remained after saving.
 */
class EditorSectionRichtextLocalesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.default_modules', [
            Dashboard::class,
            SectionsResource::class,
        ]);
        $app['config']->set('leap.locales', ['en' => 'English', 'nl' => 'Nederlands']);
    }

    protected function setUp(): void
    {
        parent::setUp();

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

        Leap::context()->setModule(SectionsResource::class);
        Leap::context()->setPermissions([
            SectionsResource::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    public function test_a_new_section_starts_its_translatable_rich_text_per_locale(): void
    {
        $record = SectionsModel::create(['title' => 'Over ons', 'sections' => []]);

        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.sections:add', 'block');

        $this->assertSame(['en' => '', 'nl' => ''], $editor->instance()->data['sections'][0]['body']);
    }

    public function test_an_empty_string_saved_earlier_is_turned_into_locales_on_opening(): void
    {
        $record = SectionsModel::create(['title' => 'Over ons', 'sections' => [
            ['_name' => 'block', '_sort' => 1, 'active' => true, 'head' => ['en' => 'Call us'], 'body' => ''],
        ]]);

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);

        $this->assertSame(['en' => '', 'nl' => ''], $editor->instance()->data['sections'][0]['body']);
    }

    public function test_the_text_of_a_new_section_is_saved_and_there_after_reopening(): void
    {
        $record = SectionsModel::create(['title' => 'Over ons', 'sections' => []]);

        Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.sections:add', 'block')
            ->set('data.sections.0.head.en', 'Ready to go?')
            ->set('data.sections.0.body.en', '<p>Book your boat today.</p>')
            ->call('save')
            ->assertHasNoErrors();

        $section = $record->fresh()->sections[0];
        $this->assertSame('Ready to go?', $section['head']['en']);
        $this->assertSame('<p>Book your boat today.</p>', $section['body']['en']);

        $reopened = Livewire::test(Editor::class)->call('openEditor', $record->id);
        $this->assertSame('<p>Book your boat today.</p>', $reopened->instance()->data['sections'][0]['body']['en']);
    }
}
