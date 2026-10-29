<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\EnumModel;
use NickDeKruijk\Leap\Tests\Fixtures\EnumRequiredDateResource;
use NickDeKruijk\Leap\Tests\Fixtures\EnumResource;
use NickDeKruijk\Leap\Tests\Fixtures\EnumStatus;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A column cast to an enum holds an enum object, and the index used it as an array key:
 * the filter of a select on such a column threw "Cannot access offset of type
 * EnumStatus on array" as soon as there was a row. Leap now reads every such value as
 * its backing value, in the index, the filter and the editor.
 *
 * Found beside it: a readonly datetime left empty blocked the save with "must be a
 * valid date", a field nobody could fill in.
 */
class EnumCastTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.default_modules', [
            Dashboard::class,
            EnumResource::class,
            EnumRequiredDateResource::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('enum_models', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('status');
            $table->string('locked_status')->nullable();
            $table->string('kind')->nullable();
            $table->dateTime('consented_at')->nullable();
            $table->dateTime('sent_at')->nullable();
        });

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        $this->module(EnumResource::class);
    }

    private function module(string $class): void
    {
        Leap::context()->setModule($class);
        Leap::context()->setPermissions([
            $class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    private function record(array $attributes = []): EnumModel
    {
        return EnumModel::create($attributes + [
            'title' => 'Aanvraag',
            'status' => EnumStatus::Draft,
            'locked_status' => EnumStatus::Sent,
            'kind' => 'boat',
        ]);
    }

    public function test_the_index_opens_with_rows_and_shows_the_label(): void
    {
        $this->record();

        $this->get(route('leap.module.enums'))
            ->assertOk()
            ->assertSee('Concept')
            ->assertSee('Boot');
    }

    public function test_the_filter_offers_the_used_values_by_their_label_in_values_order(): void
    {
        $this->record(['status' => EnumStatus::Paid]);
        $this->record(['status' => EnumStatus::Draft]);
        $this->record(['status' => EnumStatus::Paid]);

        $resource = new EnumResource;
        $attribute = $resource->indexAttributes()->firstWhere('name', 'status');

        $this->assertSame(['draft' => 'Concept', 'paid' => 'Betaald'], $resource->filterData($attribute));
    }

    public function test_a_select_without_an_enum_is_offered_by_its_label_too(): void
    {
        $this->record(['kind' => 'sup']);

        $resource = new EnumResource;
        $attribute = $resource->indexAttributes()->firstWhere('name', 'kind');

        $this->assertSame(['sup' => 'SUP-board'], $resource->filterData($attribute));
    }

    public function test_a_value_missing_from_values_is_still_offered_as_itself(): void
    {
        $this->record(['kind' => 'boat']);
        $this->record(['kind' => 'kayak']);

        $resource = new EnumResource;
        $attribute = $resource->indexAttributes()->firstWhere('name', 'kind');

        $this->assertSame(['boat' => 'Boot', 'kayak' => 'kayak'], $resource->filterData($attribute));
    }

    public function test_filtering_on_an_enum_column_finds_its_rows(): void
    {
        $this->record(['title' => 'Concept', 'status' => EnumStatus::Draft]);
        $this->record(['title' => 'Betaald', 'status' => EnumStatus::Paid]);

        $resource = new EnumResource;
        $resource->filterBy('status', 'paid');

        $this->assertSame(['Betaald'], $resource->rows(index: true)->pluck('title')->values()->all());
    }

    public function test_the_index_sorts_on_an_enum_column(): void
    {
        $this->record(['title' => 'Verstuurd', 'status' => EnumStatus::Sent]);
        $this->record(['title' => 'Concept', 'status' => EnumStatus::Draft]);

        $resource = new EnumResource;
        $resource->order('status');

        $this->assertSame(['Concept', 'Verstuurd'], $resource->rows(index: true)->pluck('title')->values()->all());
    }

    public function test_the_editor_saves_a_changed_select_through_the_cast(): void
    {
        $record = $this->record();

        $editor = Livewire::test(Editor::class)->call('openEditor', $record->id);
        $this->assertSame('draft', $editor->get('data.status'));

        $editor->set('data.status', 'paid')->call('save')->assertHasNoErrors();

        $this->assertSame('paid', DB::table('enum_models')->value('status'));
        $this->assertSame(EnumStatus::Paid, $record->fresh()->status);
    }

    public function test_a_readonly_enum_field_is_not_changed_by_a_save(): void
    {
        $record = $this->record();

        // disabled only stops the browser; a value sent by hand must not get through
        Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.status', 'sent')
            ->set('data.locked_status', 'paid')
            ->call('save')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertSame(EnumStatus::Sent, $record->status);
        $this->assertSame(EnumStatus::Sent, $record->locked_status);
    }

    public function test_an_empty_readonly_datetime_does_not_block_a_save(): void
    {
        $record = $this->record();

        Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.title', 'Gewijzigd')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Gewijzigd', $record->fresh()->title);
        $this->assertNull($record->fresh()->consented_at);
    }

    public function test_an_empty_datetime_that_is_not_required_can_be_saved(): void
    {
        $record = $this->record();

        Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.title', 'Gewijzigd')
            ->set('data.sent_at', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($record->fresh()->sent_at);
    }

    public function test_a_required_datetime_still_refuses_to_be_empty(): void
    {
        $record = $this->record();
        $this->module(EnumRequiredDateResource::class);

        Livewire::test(Editor::class)
            ->call('openEditor', $record->id)
            ->set('data.title', 'Gewijzigd')
            ->call('save')
            ->assertHasErrors(['data.sent_at' => 'required']);

        $this->assertSame('Aanvraag', $record->fresh()->title);
    }
}
