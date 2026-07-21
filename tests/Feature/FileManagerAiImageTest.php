<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The same generator from the file manager itself: a free-form prompt, stored in the
 * folder that is open, with no module to name a folder after.
 */
class FileManagerAiImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        Gate::before(fn () => true);
        $this->actingAs($user);

        config([
            'leap.ai.image.provider' => 'gemini',
            'leap.ai.providers.gemini.api_key' => 'test-key',
            'leap.ai.image.alt_text' => false,
        ]);
    }

    /**
     * A provider that answers with one image. Registered per test rather than in
     * setUp, so a test that wants a failure is not shadowed by an earlier stub.
     */
    private function fakeProvider(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($this->pngBytes())],
                ]]]]],
            ]),
        ]);
    }

    private function pngBytes(int $width = 64, int $height = 64): string
    {
        $gd = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }

    public function test_an_accepted_image_lands_in_the_open_folder_and_is_selected(): void
    {
        $this->fakeProvider();

        Storage::disk('public')->makeDirectory('photos');

        $filemanager = new FileManager;
        $filemanager->openFolders = ['photos'];

        $filemanager->useGeneratedImage($filemanager->generateImage('A yellow canoe', '1:1')['token']);

        Storage::disk('public')->assertExists('photos/a-yellow-canoe.jpg');
        $this->assertSame(['a-yellow-canoe.jpg'], $filemanager->selectedFiles);
        $this->assertNotNull(Media::where('file_name', 'photos/a-yellow-canoe.jpg')->first());
    }

    public function test_a_name_already_taken_gets_a_suffix_instead_of_overwriting(): void
    {
        $this->fakeProvider();

        $filemanager = new FileManager;

        $filemanager->useGeneratedImage($filemanager->generateImage('A yellow canoe', '1:1')['token']);
        $filemanager->useGeneratedImage($filemanager->generateImage('A yellow canoe', '1:1')['token']);

        Storage::disk('public')->assertExists('a-yellow-canoe.jpg');
        Storage::disk('public')->assertExists('a-yellow-canoe-2.jpg');
    }

    public function test_generating_leaves_no_file_behind_until_it_is_accepted(): void
    {
        $this->fakeProvider();

        (new FileManager)->generateImage('A yellow canoe', '1:1');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * The dialog is Blade + Alpine that no other test touches, and it is only rendered
     * when the task is configured — so without this it could break unnoticed.
     */
    public function test_the_dialog_is_rendered_only_when_the_task_is_configured(): void
    {
        $html = Livewire::actingAs(Auth::user())->test(FileManager::class)->html();
        $this->assertStringContainsString('leap-generate-image', $html);

        config(['leap.ai.image.provider' => null]);

        $html = Livewire::actingAs(Auth::user())->test(FileManager::class)->html();
        $this->assertStringNotContainsString('leap-generate-image', $html);
    }

    public function test_a_provider_error_returns_nothing(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('', 500)]);

        $this->assertSame([], (new FileManager)->generateImage('A yellow canoe', '1:1'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
