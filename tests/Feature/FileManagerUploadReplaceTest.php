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

    /**
     * @param  array<int, string>  $folders  The open folders, the way the file manager walks into them
     */
    private function upload(string $name, string $contents, array $folders = []): void
    {
        $file = UploadedFile::fake()->createWithContent($name, $contents);

        $fileManager = Livewire::test(FileManager::class)->instance();
        $fileManager->openFolders = $folders;
        $fileManager->uploads = ['x' => ['name' => $name, 'path' => '', 'error' => false, 'file' => $file]];
        $fileManager->uploadDone('x');
    }

    public function test_by_default_it_lands_beside_the_old_one_outside_static(): void
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

    public function test_a_list_of_folders_writes_over_in_those_folders_only(): void
    {
        config(['leap.filemanager.upload_replace' => ['static']]);
        Storage::disk('public')->put('static/logo.png', 'the first logo');
        Storage::disk('public')->put('news/logo.png', 'a news picture');
        Storage::disk('public')->put('logo.png', 'a picture in the root');

        $this->upload('logo.png', 'a new logo', ['static']);
        $this->upload('logo.png', 'another news picture', ['news']);
        $this->upload('logo.png', 'another picture in the root');

        $this->assertSame('a new logo', Storage::disk('public')->get('static/logo.png'));
        Storage::disk('public')->assertMissing('static/logo-1.png');

        $this->assertSame('a news picture', Storage::disk('public')->get('news/logo.png'));
        Storage::disk('public')->assertExists('news/logo-1.png');

        $this->assertSame('a picture in the root', Storage::disk('public')->get('logo.png'));
        Storage::disk('public')->assertExists('logo-1.png');
    }

    public function test_a_listed_folder_includes_its_subfolders_but_not_a_lookalike(): void
    {
        config(['leap.filemanager.upload_replace' => ['/static/']]);
        Storage::disk('public')->put('static/logos/logo.png', 'the first logo');
        Storage::disk('public')->put('staticky/logo.png', 'not that folder');

        $this->upload('logo.png', 'a new logo', ['static', 'logos']);
        $this->upload('logo.png', 'a new one there too', ['staticky']);

        $this->assertSame('a new logo', Storage::disk('public')->get('static/logos/logo.png'));
        $this->assertSame('not that folder', Storage::disk('public')->get('staticky/logo.png'));
        Storage::disk('public')->assertExists('staticky/logo-1.png');
    }

    public function test_a_listed_folder_still_needs_the_update_permission(): void
    {
        config(['leap.filemanager.upload_replace' => ['static']]);
        $this->permissions(['read' => true, 'create' => true, 'update' => false, 'delete' => false]);
        Storage::disk('public')->put('static/logo.png', 'the first logo');

        $this->upload('logo.png', 'a new logo', ['static']);

        $this->assertSame('the first logo', Storage::disk('public')->get('static/logo.png'));
        Storage::disk('public')->assertExists('static/logo-1.png');
    }

    public function test_by_default_it_writes_over_in_static(): void
    {
        Storage::disk('public')->put('static/logo.png', 'the first logo');
        Storage::disk('public')->put('news/logo.png', 'a news picture');

        $this->upload('logo.png', 'a new logo', ['static']);
        $this->upload('logo.png', 'another news picture', ['news']);

        $this->assertSame('a new logo', Storage::disk('public')->get('static/logo.png'));
        Storage::disk('public')->assertMissing('static/logo-1.png');
        Storage::disk('public')->assertExists('news/logo-1.png');
    }

    public function test_false_makes_the_numbered_copy_in_static_too(): void
    {
        config(['leap.filemanager.upload_replace' => false]);
        Storage::disk('public')->put('static/logo.png', 'the first logo');

        $this->upload('logo.png', 'a new logo', ['static']);

        $this->assertSame('the first logo', Storage::disk('public')->get('static/logo.png'));
        Storage::disk('public')->assertExists('static/logo-1.png');
    }
}
