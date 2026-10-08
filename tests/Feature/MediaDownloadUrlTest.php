<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The media field previews every image through the file manager download route. It
 * built that url with a path it had percent-encoded itself, and since Laravel 12.69.2
 * and 13.x escape the % in route parameters too, a space arrived as %2520 and every
 * image with a space in its path showed as broken in the editor.
 */
class MediaDownloadUrlTest extends TestCase
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
        $media = new Media;
        $media->disk = 'public';
        $media->file_name = $path;

        return $media->downloadUrl;
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
