<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use NickDeKruijk\Leap\Classes\RecordDraft;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\ArticleResource;
use NickDeKruijk\Leap\Tests\Fixtures\PreviewableModel;
use NickDeKruijk\Leap\Tests\Fixtures\PreviewableResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The button in the editor toolbar: there when there is something to preview, absent
 * when there is not, and pointing at the language tab you are actually on.
 */
class EditorPreviewButtonTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.default_modules', [
            Dashboard::class,
            PreviewableResource::class,
            ArticleResource::class,
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

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);
    }

    private function grantAll(string $module): void
    {
        Leap::context()->setModule($module);
        Leap::context()->setPermissions([
            $module => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    private function record(): PreviewableModel
    {
        return PreviewableModel::create(['title' => ['nl' => 'Over ons', 'en' => 'About us'], 'active' => true]);
    }

    public function test_a_saved_previewable_record_gets_a_url(): void
    {
        $this->grantAll(PreviewableResource::class);
        $record = $this->record();

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);

        $this->assertSame(
            route('leap.preview', ['module' => 'previews', 'id' => $record->id]),
            $editor->instance()->previewUrl()
        );
        $editor->assertSee('leap-preview', false);
    }

    public function test_there_is_nothing_to_preview_for_a_new_record(): void
    {
        $this->grantAll(PreviewableResource::class);

        $editor = Livewire::test(Editor::class)->call('openEditor', Editor::CREATE_NEW);

        $this->assertNull($editor->instance()->previewUrl());
    }

    /**
     * Without the contract the frontend has no idea how to render the record, so
     * there is nothing to link to.
     */
    public function test_a_model_that_cannot_render_itself_gets_no_url(): void
    {
        $this->grantAll(ArticleResource::class);
        $article = (new ArticleResource)->getModel()->create(['title' => ['nl' => 'Nieuws']]);

        $editor = Livewire::test(Editor::class)->call('openEditor', $article->id);

        $this->assertNull($editor->instance()->previewUrl());
    }

    public function test_the_url_follows_the_active_language_tab(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $this->grantAll(PreviewableResource::class);
        $record = $this->record();

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);

        $this->assertStringEndsWith('/nl', $editor->instance()->previewUrl());

        $editor->set('activeLocale', 'en');

        $this->assertStringEndsWith('/en', $editor->instance()->previewUrl());
    }

    public function test_a_monolingual_editor_leaves_the_locale_out(): void
    {
        config(['leap.locales' => null]);
        $this->grantAll(PreviewableResource::class);
        $record = $this->record();

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);

        $this->assertStringEndsWith('/previews/'.$record->id, $editor->instance()->previewUrl());
    }

    /**
     * The click stashes the form before opening the tab, so the preview shows what is
     * on screen rather than what was last saved.
     */
    public function test_the_button_stashes_the_current_form(): void
    {
        $this->grantAll(PreviewableResource::class);
        $record = $this->record();

        Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.body', 'Nog niet opgeslagen')
            ->call('stashPreview');

        $stash = session('leap.preview');

        $this->assertSame('previews', $stash['module']);
        $this->assertSame($record->id, $stash['id']);
        $this->assertSame('Nog niet opgeslagen', $stash['data']['body']);
    }

    public function test_stashing_needs_read_permission(): void
    {
        $this->grantAll(PreviewableResource::class);
        $record = $this->record();
        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);

        Leap::context()->setPermissions([PreviewableResource::class => ['read' => false]]);

        $editor->call('stashPreview')->assertForbidden();
        $this->assertNull(session('leap.preview'));
    }

    public function test_a_second_stash_replaces_the_first(): void
    {
        RecordDraft::stash('previews', 1, ['title' => 'first']);
        RecordDraft::stash('previews', 2, ['title' => 'second']);

        $this->assertSame(2, session('leap.preview')['id']);
        $this->assertSame('second', session('leap.preview')['data']['title']);
    }
}
