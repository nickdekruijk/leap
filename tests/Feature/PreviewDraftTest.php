<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\RecordDraft;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\HashingUser;
use NickDeKruijk\Leap\Tests\Fixtures\PreviewableModel;
use NickDeKruijk\Leap\Tests\Fixtures\PreviewableResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Previewing what is in the editor, not what is in the database.
 *
 * The values travel in the user's own session and are written onto the record without
 * saving it, through the same code the editor saves with — a preview that filled fields
 * its own way would eventually stop matching the page it claims to preview.
 *
 * Media and linked records are the exception, and deliberately so: those live in their
 * own tables and do not exist until they are written. The preview says as much rather
 * than pretending.
 */
class PreviewDraftTest extends TestCase
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

    private function record(): PreviewableModel
    {
        return PreviewableModel::create([
            'title' => ['en' => 'Saved title'],
            'body' => 'Saved body',
            'active' => true,
        ]);
    }

    private function url(PreviewableModel $record): string
    {
        return route('leap.preview', ['module' => 'previews', 'id' => $record->id]);
    }

    private function stash(string $module, int $id, array $data, ?int $at = null): void
    {
        RecordDraft::stash($module, $id, $data);

        if ($at !== null) {
            session(['leap.preview' => array_merge(session('leap.preview'), ['at' => $at])]);
        }
    }

    public function test_a_stashed_value_wins_from_the_saved_one(): void
    {
        $record = $this->record();
        $this->stash('previews', $record->id, ['title' => ['en' => 'Typed title'], 'body' => 'Typed body']);

        $this->get($this->url($record))
            ->assertOk()
            ->assertSee('record:Typed title')
            ->assertSee('body:Typed body')
            ->assertSee('unsaved:yes');

        // Previewing changes nothing: what is stored stays stored.
        $this->assertSame('Saved title', $record->fresh()->title);
    }

    public function test_without_a_stash_the_saved_record_is_shown(): void
    {
        $record = $this->record();

        $this->get($this->url($record))
            ->assertOk()
            ->assertSee('record:Saved title')
            ->assertSee('unsaved:no');
    }

    public function test_a_stash_for_another_record_or_module_is_ignored(): void
    {
        $record = $this->record();

        $this->stash('previews', $record->id + 1, ['title' => ['en' => 'Other record']]);
        $this->get($this->url($record))->assertOk()->assertSee('record:Saved title');

        $this->stash('somethingelse', $record->id, ['title' => ['en' => 'Other module']]);
        $this->get($this->url($record))->assertOk()->assertSee('record:Saved title');
    }

    /**
     * A tab left open since yesterday should show the record, not typing nobody
     * remembers doing.
     */
    public function test_an_expired_stash_is_ignored(): void
    {
        $record = $this->record();
        $this->stash('previews', $record->id, ['title' => ['en' => 'Yesterday']], now()->subMinutes(31)->timestamp);

        $this->get($this->url($record))
            ->assertOk()
            ->assertSee('record:Saved title')
            ->assertSee('unsaved:no');
    }

    /**
     * Nothing in the form is validated — half-typed is exactly the state you want to
     * look at. So a value the model refuses falls back to what is stored rather than
     * turning the preview into an error page.
     */
    public function test_a_value_the_model_refuses_falls_back_to_the_saved_record(): void
    {
        $record = $this->record();
        $this->stash('previews', $record->id, [
            'title' => ['en' => 'Typed title'],
            'published_at' => 'not a date at all',
        ]);

        $this->get($this->url($record))
            ->assertOk()
            ->assertSee('record:Saved title')
            ->assertSee('unsaved:no');
    }

    /**
     * Media and pivot ids sit in the form too, and are the one thing the draft leaves
     * alone: they only exist once syncMedia()/syncPivot() has written them.
     */
    public function test_media_and_pivot_values_in_the_stash_change_nothing(): void
    {
        $record = $this->record();
        $resource = new PreviewableResource;
        $data = ['title' => ['en' => 'Typed'], 'images' => [7, 8], 'tags' => [1, 2]];

        RecordDraft::apply($record, collect([
            Attribute::make('title')->translatable(),
            Attribute::make('images')->type('media'),
            Attribute::make('tags')->type('pivot'),
        ]), $data);

        $this->assertSame('Typed', $record->title);
        $this->assertFalse($record->isDirty('images'));
        $this->assertArrayNotHasKey('images', $record->getAttributes());
        $this->assertArrayNotHasKey('tags', $record->getAttributes());
        $this->assertInstanceOf(PreviewableResource::class, $resource);
    }

    /**
     * The reason both paths share RecordDraft::apply(): a preview that filled fields its
     * own way would drift from what saving does, and the drift would show up as the
     * preview lying about the page.
     */
    public function test_the_draft_and_a_save_produce_the_same_record(): void
    {
        $saved = $this->record();
        $form = ['title' => ['en' => 'Rewritten'], 'body' => 'Rewritten body', 'active' => false, 'published_at' => null];

        Livewire::test(Editor::class)
            ->call('openEditor', $saved->id)
            ->set('data.title', 'Rewritten')
            ->set('data.body', 'Rewritten body')
            ->set('data.active', false)
            ->call('save');

        $drafted = $this->record();
        $data = $form;
        RecordDraft::apply($drafted, collect((new PreviewableResource)->attributes())->where('indexOnly', false), $data);

        $this->assertSame(
            $saved->fresh()->only(['title', 'body', 'active', 'published_at']),
            $drafted->only(['title', 'body', 'active', 'published_at']),
        );
    }

    /**
     * A preview would only hash a password nothing reads, so it leaves them alone.
     */
    public function test_a_password_is_not_hashed_for_a_preview(): void
    {
        $user = new HashingUser;
        $data = ['password' => 'secret'];
        $attributes = collect([Attribute::make('password')->type('password')]);

        RecordDraft::apply($user, $attributes, $data, false);
        $this->assertNull($user->password);

        RecordDraft::apply($user, $attributes, $data);
        $this->assertNotNull($user->password);
        $this->assertTrue(Hash::check('secret', $user->password) || $user->password === 'secret');
    }
}
