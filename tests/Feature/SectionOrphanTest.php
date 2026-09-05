<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
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
 * A stored section whose _name no longer matches any Section in the resource: renamed or
 * removed in app/Leap while records kept it. The editor has no fields to draw for it, so
 * it dumps the raw key/value pairs instead of hiding the content.
 *
 * That dump assumed every value was a string. A section field is just as often an array —
 * a translation set, a media picker, a json attribute — and e() calls htmlspecialchars(),
 * which fataled the whole request with "Argument #1 ($string) must be of type string,
 * array given". One orphan section made the record unopenable.
 */
class SectionOrphanTest extends TestCase
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

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function open(array $sections): Testable
    {
        $record = SectionsModel::create(['title' => 'Over ons', 'sections' => $sections]);

        return Livewire::test(Editor::class)->call('openEditor', $record->id);
    }

    public function test_an_orphan_section_holding_an_array_still_renders(): void
    {
        $editor = $this->open([[
            '_name' => 'was_removed',
            '_sort' => 1,
            'head' => ['nl' => 'Kop', 'en' => 'Heading'],
        ]]);

        $editor->assertOk();
        $editor->assertSee('head: {"nl":"Kop","en":"Heading"}');
    }

    public function test_a_scalar_value_is_shown_as_it_is(): void
    {
        $editor = $this->open([[
            '_name' => 'was_removed',
            '_sort' => 1,
            'head' => 'Plain string',
            'columns' => 3,
        ]]);

        $editor->assertOk();
        $editor->assertSee('head: Plain string');
        $editor->assertSee('columns: 3');
    }
}
