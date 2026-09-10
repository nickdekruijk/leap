<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Livewire normally hydrates uploads[$id]['file'] as a TemporaryUploadedFile, but
 * an update that resends uploads[$id] as a whole loses the synth meta and the
 * file arrives as the bare "livewire-file:<name>" string (SEDATE-39).
 */
class FileManagerUploadReferenceTest extends TestCase
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

    protected function uploadDoneWith(string $reference): void
    {
        $fm = Livewire::test(FileManager::class)->instance();
        $fm->uploads = ['x' => [
            'name' => 'photo.jpg',
            'path' => '',
            'error' => false,
            'file' => $reference,
        ]];

        $fm->uploadDone('x');
    }

    public function test_a_bare_temporary_file_reference_is_stored_like_an_uploaded_file(): void
    {
        FileUploadConfiguration::storage()->put(FileUploadConfiguration::path('tmp-photo.jpg'), 'jpeg-bytes');

        $this->uploadDoneWith('livewire-file:tmp-photo.jpg');

        Storage::disk('public')->assertExists('photo.jpg');
        $this->assertSame('jpeg-bytes', Storage::disk('public')->get('photo.jpg'));
    }

    public function test_a_signed_temporary_file_reference_is_stored_like_an_uploaded_file(): void
    {
        // Livewire 4.4 and later send the reference as "livewire-file:<token>:<name>".
        FileUploadConfiguration::storage()->put(FileUploadConfiguration::path('tmp-photo.jpg'), 'jpeg-bytes');

        $this->uploadDoneWith('livewire-file:'.TemporaryUploadedFile::signPath('tmp-photo.jpg'));

        Storage::disk('public')->assertExists('photo.jpg');
        $this->assertSame('jpeg-bytes', Storage::disk('public')->get('photo.jpg'));
    }

    public function test_a_reference_with_a_forged_token_is_refused(): void
    {
        FileUploadConfiguration::storage()->put(FileUploadConfiguration::path('tmp-photo.jpg'), 'jpeg-bytes');

        $this->uploadDoneWith('livewire-file:deadbeef:tmp-photo.jpg');

        Storage::disk('public')->assertMissing('photo.jpg');
    }

    public function test_a_reference_to_a_missing_temporary_file_stores_nothing(): void
    {
        $this->uploadDoneWith('livewire-file:never-uploaded.jpg');

        Storage::disk('public')->assertMissing('photo.jpg');
    }

    public function test_a_reference_with_path_segments_is_refused(): void
    {
        FileUploadConfiguration::storage()->put('outside.jpg', 'secret');

        $this->uploadDoneWith('livewire-file:../outside.jpg');

        Storage::disk('public')->assertMissing('photo.jpg');
    }

    public function test_an_arbitrary_string_stores_nothing(): void
    {
        $this->uploadDoneWith('not-a-reference');

        Storage::disk('public')->assertMissing('photo.jpg');
    }
}
