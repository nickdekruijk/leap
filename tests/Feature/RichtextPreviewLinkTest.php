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
 * The click-to-edit preview of a rich-text field in a section.
 *
 * The preview renders the stored HTML as it is, so a link in it is a real link. A click
 * on an image with a link around it followed the link instead of opening the editor,
 * and the field could not be edited at all once the image filled the preview.
 */
class RichtextPreviewLinkTest extends TestCase
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

    public function test_a_click_on_the_preview_never_follows_a_link_in_it(): void
    {
        $record = SectionsModel::create([
            'title' => 'Over ons',
            'sections' => [
                ['_name' => 'block', 'head' => 'Partners', 'body' => '<p><a href="https://example.com"><img src="logo.jpg"></a></p>'],
            ],
        ]);

        Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->assertSeeHtml('class="leap-richtext-preview tinymce"')
            ->assertSeeHtml('@click.prevent="activate()"')
            ->assertDontSeeHtml('@click="activate()"');
    }

    public function test_links_in_the_preview_take_no_pointer_events(): void
    {
        $css = file_get_contents(__DIR__.'/../../resources/css/editor.css');
        $start = strpos($css, '.leap .leap-richtext-preview {');

        $this->assertNotFalse($start);
        $this->assertMatchesRegularExpression('/\ba\s*\{\s*pointer-events:\s*none;/', substr($css, $start, 3000));
    }
}
