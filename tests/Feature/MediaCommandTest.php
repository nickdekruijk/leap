<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Mediable;
use NickDeKruijk\Leap\Tests\Fixtures\MediaModel;
use NickDeKruijk\Leap\Tests\Fixtures\ScopedMediaModel;
use NickDeKruijk\Leap\Tests\Fixtures\SoftDeleteMediaModel;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * leap:media cleans up after the deletes model events never saw: the links left
 * behind before leap took them along itself, a mass delete, a truncate, an import
 * that renumbers. Every judgement it makes is about one question, is the model
 * still there, and it has to answer that without a scope hiding the answer.
 */
class MediaCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('media_models', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
        });

        Schema::create('soft_delete_media_models', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->softDeletes();
        });

        Schema::create('scoped_media_models', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->boolean('active')->default(true);
        });

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    private function pngBytes(int $width = 10, int $height = 10): string
    {
        $gd = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }

    private function media(string $file = 'header.png'): Media
    {
        Storage::disk('public')->put($file, $this->pngBytes());

        return Media::forFile($file);
    }

    /**
     * Written straight into the pivot, because that is how the rows this command
     * is for came to exist: nobody attached them to a model that is there.
     */
    private function link(string $type, int|string $id, string $file = 'header.png'): Mediable
    {
        return Mediable::create([
            'media_id' => $this->media($file)->id,
            'mediable_type' => $type,
            'mediable_id' => $id,
            'mediable_attribute' => 'header',
            'sort' => 0,
        ]);
    }

    private function attach(Model $model, string $file = 'header.png'): Mediable
    {
        return $this->link($model->getMorphClass(), $model->id, $file);
    }

    public function test_it_prunes_a_link_whose_model_is_gone(): void
    {
        $this->link(MediaModel::class, 999);

        $this->artisan('leap:media --prune')->assertSuccessful();

        $this->assertSame(0, Mediable::count());
    }

    public function test_it_leaves_a_link_whose_model_is_still_there(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model);

        $this->artisan('leap:media --prune')->assertSuccessful();

        $this->assertSame(1, Mediable::count());
    }

    /**
     * A soft deleted record can be restored, and it has to come back with the
     * gallery it was deleted with.
     */
    public function test_a_soft_deleted_model_still_counts_as_in_use(): void
    {
        $model = SoftDeleteMediaModel::create(['title' => 'Post']);
        $this->attach($model);

        $model->delete();

        $this->artisan('leap:media --prune')->assertSuccessful();

        $this->assertSame(1, Mediable::count());
    }

    /**
     * A project's own global scope hides records that are very much still there:
     * unpublished, another tenant's, another locale's. Asking through the scope
     * would call every one of them an orphan and empty their galleries.
     */
    public function test_a_global_scope_does_not_make_a_model_look_gone(): void
    {
        $model = ScopedMediaModel::create(['title' => 'Post', 'active' => false]);
        $this->attach($model);

        $this->assertNull(ScopedMediaModel::find($model->id));

        $this->artisan('leap:media --prune')->assertSuccessful();

        $this->assertSame(1, Mediable::count());
    }

    public function test_a_link_whose_class_no_longer_exists_is_reported_but_kept(): void
    {
        $this->link('App\Models\Gone', 1);

        $this->artisan('leap:media --prune')
            ->expectsOutputToContain('App\Models\Gone')
            ->assertSuccessful();

        $this->assertSame(1, Mediable::count());
    }

    public function test_an_unknown_class_is_pruned_only_when_asked(): void
    {
        $this->link('App\Models\Gone', 1);

        $this->artisan('leap:media --prune --unknown')->assertSuccessful();

        $this->assertSame(0, Mediable::count());
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        $this->link(MediaModel::class, 999);

        $this->artisan('leap:media --prune --dry-run')->assertSuccessful();

        $this->assertSame(1, Mediable::count());
    }

    public function test_without_prune_it_only_reports(): void
    {
        $this->link(MediaModel::class, 999);

        $this->artisan('leap:media')
            ->expectsOutputToContain('orphaned')
            ->assertSuccessful();

        $this->assertSame(1, Mediable::count());
    }

    /**
     * With a morph map the pivot holds the alias, and the table to weigh it
     * against is the one the map points at.
     */
    public function test_a_morph_alias_is_resolved_to_its_model(): void
    {
        Relation::morphMap(['media_model' => MediaModel::class]);

        $model = MediaModel::create(['title' => 'Post']);
        $this->link('media_model', $model->id, 'kept.png');
        $this->link('media_model', 999, 'gone.png');

        $this->artisan('leap:media --prune')->assertSuccessful();

        $this->assertSame(1, Mediable::count());
        $this->assertSame($model->id, (int) Mediable::first()->mediable_id);
    }

    /**
     * The file is still on disk, and a media row with no links left is exactly
     * what the file manager needs in order to be allowed to delete it. Deleting
     * the row here would take that away again.
     */
    public function test_pruning_leaves_the_media_rows_alone(): void
    {
        $this->link(MediaModel::class, 999);

        $this->artisan('leap:media --prune')->assertSuccessful();

        $this->assertSame(1, Media::count());
        $this->assertTrue(Storage::disk('public')->exists('header.png'));
    }
}
