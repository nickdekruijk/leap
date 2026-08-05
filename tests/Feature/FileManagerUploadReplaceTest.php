<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * What happens when an upload has the name of a file that is already there.
 *
 * The numbered copy is the default and stays the default: writing over a file
 * changes every page showing it at once, which is right when someone means "new
 * version of this picture" and wrong when they mean "another photo that happens
 * to be called header.jpg".
 */
class FileManagerUploadReplaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['leap.filemanager.allowed_extensions' => ['jpg', 'png']]);
        Storage::fake('public');

        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password' => 'x']));
        $this->permissions(['read' => true, 'create' => true, 'update' => true, 'delete' => true]);
    }

    private function permissions(array $permissions): void
    {
        Leap::context()->setModule(FileManager::class)->setPermissions([
            FileManager::class => $permissions,
        ]);
    }

    private function upload(string $name, string $contents): void
    {
        $file = UploadedFile::fake()->createWithContent($name, $contents);

        $fileManager = Livewire::test(FileManager::class)->instance();
        $fileManager->uploads = ['x' => ['name' => $name, 'path' => '', 'error' => false, 'file' => $file]];
        $fileManager->uploadDone('x');
    }

    public function test_by_default_it_lands_beside_the_old_one(): void
    {
        Storage::disk('public')->put('pic.jpg', 'the first picture');
        $media = Media::forFile('pic.jpg');

        $this->upload('pic.jpg', 'a different picture');

        Storage::disk('public')->assertExists('pic-1.jpg');
        $this->assertSame('the first picture', Storage::disk('public')->get('pic.jpg'));
        $this->assertSame($media->sha256, $media->fresh()->sha256);
    }

    public function test_with_upload_replace_it_writes_over_the_old_one(): void
    {
        config(['leap.filemanager.upload_replace' => true]);
        Storage::disk('public')->put('pic.jpg', 'the first picture');
        $media = Media::forFile('pic.jpg');

        $this->upload('pic.jpg', 'a different picture');

        Storage::disk('public')->assertMissing('pic-1.jpg');
        $this->assertSame('a different picture', Storage::disk('public')->get('pic.jpg'));

        // The same row, re-read: everything pointing at this media goes on
        // pointing at it and now shows the new picture.
        $this->assertSame($media->id, Media::findFile('pic.jpg')->id);
        $this->assertSame(hash('sha256', 'a different picture'), $media->fresh()->sha256);
    }

    public function test_identical_contents_are_refused_either_way(): void
    {
        config(['leap.filemanager.upload_replace' => true]);
        Storage::disk('public')->put('pic.jpg', 'the first picture');
        Media::forFile('pic.jpg');

        $this->upload('pic.jpg', 'the first picture');

        Storage::disk('public')->assertMissing('pic-1.jpg');
        $this->assertSame(1, Media::where('file_name', 'like', 'pic%')->count());
    }

    public function test_someone_who_may_only_add_files_cannot_replace_one(): void
    {
        config(['leap.filemanager.upload_replace' => true]);
        $this->permissions(['read' => true, 'create' => true, 'update' => false, 'delete' => false]);
        Storage::disk('public')->put('pic.jpg', 'the first picture');

        $this->upload('pic.jpg', 'a different picture');

        // Writing over a picture pages are already showing is an update, so
        // without that permission the upload falls back to the numbered copy.
        Storage::disk('public')->assertExists('pic-1.jpg');
        $this->assertSame('the first picture', Storage::disk('public')->get('pic.jpg'));
    }
}
