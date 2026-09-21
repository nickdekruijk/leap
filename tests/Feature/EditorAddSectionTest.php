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
 * Adding a section from the select above the list.
 *
 * The select opens on an empty placeholder option, so it can hand the server a value
 * that names no section at all — which used to reach ->attributes on null and throw.
 */
class EditorAddSectionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.default_modules', [
            Dashboard::class,
            SectionsResource::class,
        ]);
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

    private function record(): SectionsModel
    {
        return SectionsModel::create(['title' => 'Over ons', 'sections' => []]);
    }

    public function test_it_adds_a_section_with_its_default_values(): void
    {
        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $this->record()->id)
            ->set('data.sections:add', 'block');

        $sections = $editor->instance()->data['sections'];

        $this->assertCount(1, $sections);
        $this->assertSame('block', $sections[0]['_name']);
        $this->assertTrue($sections[0]['active']);
        $editor->assertSet('data.sections:add', null);
    }

    public function test_the_empty_placeholder_option_adds_nothing(): void
    {
        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $this->record()->id)
            ->set('data.sections:add', '');

        $this->assertSame([], $editor->instance()->data['sections'] ?? []);
        $editor->assertSet('data.sections:add', null);
    }

    /**
     * A section the module no longer declares, as a stale browser tab would still offer.
     */
    public function test_an_unknown_section_name_adds_nothing(): void
    {
        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $this->record()->id)
            ->set('data.sections:add', 'removed-section');

        $this->assertSame([], $editor->instance()->data['sections'] ?? []);
    }
}
