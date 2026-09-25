<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The file manager previews every image through its download route, with a path it
 * has already percent-encoded itself. Laravel 12.69.2 and 13.x escape the % in route
 * parameters as well, so that path was encoded twice: a space arrived as %2520, the
 * file was looked up as "portret%20fotos/..." and every image in a folder with a space
 * in its name showed as broken.
 */
class FileManagerDownloadUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $user = User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password' => 'x']);
        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        Leap::context()->setModule(FileManager::class)->setPermissions([
            FileManager::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    private function downloadUrl(string $path): string
    {
        return Livewire::test(FileManager::class)->instance()->downloadUrl($path);
    }

    public function test_a_path_with_spaces_is_encoded_once(): void
    {
        Storage::disk('public')->put('portret fotos/Inge Lankes.jpg', 'jpeg');

        $url = $this->downloadUrl('portret fotos/Inge Lankes.jpg');

        $this->assertStringEndsWith('/download/portret%20fotos/Inge%20Lankes.jpg', $url);
        $this->get($url)->assertOk();
    }

    public function test_a_percent_sign_and_a_hash_reach_the_file(): void
    {
        Storage::disk('public')->put('sale/50% off #1.png', 'png');

        $url = $this->downloadUrl('sale/50% off #1.png');

        $this->assertStringEndsWith('/download/sale/50%25%20off%20%231.png', $url);
        $this->get($url)->assertOk();
    }
}
