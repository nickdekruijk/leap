<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class FileManagerUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['leap.filemanager.allowed_extensions' => ['jpg', 'png']]);
        Storage::fake('public');

        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password' => 'x']));
        Leap::context()->setModule(FileManager::class)->setPermissions([
            FileManager::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    /**
     * A hostile client can set the public $uploads array directly with error=false /
     * a forged name+path, so the checks in uploadStart must be re-enforced in
     * uploadDone. We set the props on the booted instance and call the method
     * directly, because an UploadedFile can't survive Livewire's snapshot between
     * ->call() round-trips (the client normally sends a temp-file reference).
     */
    public function test_upload_rejects_a_disallowed_extension_even_when_the_client_marks_it_valid(): void
    {
        $fm = Livewire::test(FileManager::class)->instance();
        $fm->uploads = ['x' => [
            'name' => 'evil.php',
            'path' => '',
            'error' => false,
            'file' => UploadedFile::fake()->create('evil.php', 10),
        ]];

        $fm->uploadDone('x');

        Storage::disk('public')->assertMissing('evil.php');
    }

    public function test_upload_stores_an_allowed_file_at_the_server_computed_path(): void
    {
        // The target path is rebuilt from the open folders server-side, not taken from
        // the client's uploads[...]['path'].
        $fm = Livewire::test(FileManager::class)->instance();
        $fm->uploads = ['x' => [
            'name' => 'ok.jpg',
            'path' => 'somewhere/else',
            'error' => false,
            'file' => UploadedFile::fake()->image('ok.jpg'),
        ]];

        $fm->uploadDone('x');

        Storage::disk('public')->assertExists('ok.jpg');
        Storage::disk('public')->assertMissing('somewhere/else/ok.jpg');
    }
}
