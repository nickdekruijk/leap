<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class FileManagerHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['leap.filemanager.allowed_extensions' => ['jpg', 'png', 'svg']]);
        Storage::fake('public');

        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password' => 'x']));
        Leap::context()->setModule(FileManager::class)->setPermissions([
            FileManager::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    public function test_bytes_accepts_lowercase_size_suffixes(): void
    {
        $fm = Livewire::test(FileManager::class)->instance();

        $this->assertSame(20 * 1024 * 1024, $fm->bytes('20m'));
        $this->assertSame(20 * 1024 * 1024, $fm->bytes('20M'));
        $this->assertSame(1024, $fm->bytes('1k'));
        $this->assertSame(3 * 1024 ** 3, $fm->bytes('3g'));
        $this->assertSame(512, $fm->bytes('512'));
    }

    public function test_sanitize_svg_strips_active_content(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            .'<script>alert(1)</script>'
            .'<foreignObject><body onload="alert(2)"></body></foreignObject>'
            .'<rect width="10" height="10" onclick="alert(3)" fill="red"/>'
            .'<a href="javascript:alert(4)"><text>x</text></a>'
            .'</svg>';

        $clean = FileManager::sanitizeSvg($svg);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('foreignObject', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('<rect width="10" height="10"', $clean);
        $this->assertStringContainsString('fill="red"', $clean);
    }

    public function test_uploaded_svg_is_stored_sanitized(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="5"/></svg>';

        $fm = Livewire::test(FileManager::class)->instance();
        $fm->uploads = ['x' => [
            'name' => 'logo.svg',
            'path' => '',
            'error' => false,
            'file' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
        ]];

        $fm->uploadDone('x');

        Storage::disk('public')->assertExists('logo.svg');
        $stored = Storage::disk('public')->get('logo.svg');
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringContainsString('<circle r="5"/>', $stored);
    }

    public function test_delete_refuses_a_client_supplied_traversal_path(): void
    {
        // $selectedFiles is a public Livewire property, so a hostile client can put
        // a dot-segment in it; the server must refuse to act on it.
        Storage::disk('public')->put('secret.txt', 'keep me');
        Storage::disk('public')->makeDirectory('sub');

        $fm = Livewire::test(FileManager::class)->instance();
        $fm->openFolders = ['sub'];
        $fm->selectedFiles = [0 => '../secret.txt'];

        $fm->deleteFiles();

        Storage::disk('public')->assertExists('secret.txt');
    }

    public function test_delete_directory_refuses_traversal_in_open_folders(): void
    {
        Storage::disk('public')->makeDirectory('safe');

        $fm = Livewire::test(FileManager::class)->instance();
        $fm->openFolders = ['..'];

        $this->assertFalse($fm->deleteDirectory(1));
    }
}
