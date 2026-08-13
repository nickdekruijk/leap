<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Mediable;
use NickDeKruijk\Leap\Tests\Fixtures\MediaModel;
use NickDeKruijk\Leap\Tests\Fixtures\SoftDeleteMediaModel;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Media links have to go when their model does, and only then.
 *
 * Left behind they do two kinds of damage. The file manager refuses to delete the
 * file forever, because it counts the links and finds one, and the editor cannot
 * see whose it is because that record no longer exists. And ids restart at 1 after
 * a migrate:fresh, so the next record to be given number 12 inherits the pictures
 * of the one that had it before.
 *
 * The other half is the trap: nearly every content model soft deletes, and a
 * deleted record can be restored. Detaching there would empty the gallery of a
 * record that is coming back.
 */
class HasMediaDeleteTest extends TestCase
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

    private function attach(Model $model, string $file, string $attribute = 'header', int $sort = 0): Media
    {
        Storage::disk('public')->put($file, $this->pngBytes());

        $media = Media::forFile($file);

        Mediable::create([
            'media_id' => $media->id,
            'mediable_type' => $model->getMorphClass(),
            'mediable_id' => $model->id,
            'mediable_attribute' => $attribute,
            'sort' => $sort,
        ]);

        return $media;
    }

    private function links(Model $model): int
    {
        return Mediable::where('mediable_type', $model->getMorphClass())
            ->where('mediable_id', $model->id)
            ->count();
    }

    public function test_deleting_a_model_takes_its_media_links_with_it(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png');
        $this->attach($model, 'gallery-1.png', 'gallery');

        $this->assertSame(2, $this->links($model));

        $model->delete();

        $this->assertSame(0, $this->links($model));
    }

    /**
     * The Media row stays: the file is still on disk, and a media row with no
     * links left is precisely what the file manager needs in order to be allowed
     * to delete it. Left with a link, that file could never be deleted again.
     */
    public function test_the_media_row_survives_so_the_file_can_still_be_deleted(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $media = $this->attach($model, 'header.png');

        $model->delete();

        $media = Media::find($media->id);

        $this->assertNotNull($media);
        $this->assertSame(0, $media->mediables()->count());
    }

    public function test_a_soft_deleted_model_keeps_its_gallery(): void
    {
        $model = SoftDeleteMediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png');

        $model->delete();

        $this->assertSame(1, $this->links($model));
        $this->assertCount(1, SoftDeleteMediaModel::withTrashed()->find($model->id)->mediaFor('header'));
    }

    public function test_a_restored_model_comes_back_with_its_gallery(): void
    {
        $model = SoftDeleteMediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png');

        $model->delete();
        SoftDeleteMediaModel::withTrashed()->find($model->id)->restore();

        $this->assertSame('header.png', SoftDeleteMediaModel::find($model->id)->mediaFile('header'));
    }

    public function test_force_deleting_takes_the_media_links_with_it(): void
    {
        $model = SoftDeleteMediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png');

        $model->forceDelete();

        $this->assertSame(0, $this->links($model));
    }

    /**
     * The usual way a record is really deleted: it goes to the bin first and is
     * emptied out of it later. SoftDeletes sets the force flag before calling
     * delete() again, so the hook still sees it.
     */
    public function test_force_deleting_a_model_that_was_already_trashed_takes_the_links_with_it(): void
    {
        $model = SoftDeleteMediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png');

        $model->delete();
        SoftDeleteMediaModel::withTrashed()->find($model->id)->forceDelete();

        $this->assertSame(0, $this->links($model));
    }

    /**
     * Media rows are keyed on the file, so two models pointing at one picture is
     * normal rather than exotic. Deleting one must not empty the other.
     */
    public function test_another_models_links_are_left_alone(): void
    {
        $first = MediaModel::create(['title' => 'First']);
        $second = MediaModel::create(['title' => 'Second']);

        $media = $this->attach($first, 'shared.png');
        Mediable::create([
            'media_id' => $media->id,
            'mediable_type' => $second->getMorphClass(),
            'mediable_id' => $second->id,
            'mediable_attribute' => 'header',
            'sort' => 0,
        ]);

        $first->delete();

        $this->assertSame('shared.png', $second->fresh()->mediaFile('header'));
        $this->assertNotNull(Media::find($media->id));
    }

    /**
     * The editor writes mediable_type as the class name, while the relation reads
     * it through the morph map. Without a map those are the same string; with one
     * they are not, and every row written before it was added still carries the
     * class name. Both have to go.
     */
    public function test_links_written_under_either_morph_name_are_taken_along(): void
    {
        Relation::morphMap(['media_model' => MediaModel::class]);

        $model = MediaModel::create(['title' => 'Post']);
        $media = $this->attach($model, 'header.png');

        Mediable::create([
            'media_id' => $media->id,
            'mediable_type' => MediaModel::class,
            'mediable_id' => $model->id,
            'mediable_attribute' => 'gallery',
            'sort' => 0,
        ]);

        $this->assertSame('media_model', $model->getMorphClass());
        $this->assertSame(2, Mediable::where('mediable_id', $model->id)->count());

        $model->delete();

        $this->assertSame(0, Mediable::where('mediable_id', $model->id)->count());
    }

    /**
     * A mass delete goes straight to the database and fires no model events, so
     * nothing here can see it. Asserted on purpose: it is the documented gap that
     * leap:media --prune exists for.
     */
    public function test_a_mass_delete_leaves_the_links_behind(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png');

        MediaModel::where('title', 'Post')->delete();

        $this->assertSame(1, $this->links($model));
    }

    /**
     * The payoff, end to end: the file manager counts the links on a media row to
     * decide whether a file is still in use. A link nobody owns any more made that
     * count wrong forever, so the file could never be deleted and nothing on the
     * screen could say why.
     */
    public function test_the_file_manager_can_delete_the_file_once_its_model_is_gone(): void
    {
        config(['leap.filemanager.allowed_extensions' => ['png']]);

        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password' => 'x']));
        Leap::context()->setModule(FileManager::class)->setPermissions([
            FileManager::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);

        $model = SoftDeleteMediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png');

        $model->forceDelete();

        $fm = Livewire::test(FileManager::class)->instance();
        $fm->selectedFiles = [0 => 'header.png'];
        $fm->deleteFiles();

        Storage::disk('public')->assertMissing('header.png');
    }

    public function test_detach_all_media_can_be_called_by_hand(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png');
        $this->attach($model, 'gallery-1.png', 'gallery');

        $this->assertSame(2, $model->detachAllMedia());
        $this->assertSame(0, $this->links($model));
    }
}
