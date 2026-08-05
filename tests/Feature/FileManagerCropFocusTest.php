<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class FileManagerCropFocusTest extends TestCase
{
    public function test_true_enables_every_bitmap_format_but_excludes_svg(): void
    {
        config(['leap.filemanager.image_crop_enabled' => true]);
        config(['leap.filemanager.image_focus_enabled' => true]);

        $fileManager = new FileManager;

        foreach (['photo.jpg', 'photo.jpeg', 'photo.png', 'photo.gif', 'photo.webp'] as $file) {
            $this->assertTrue($fileManager->imageCropEnabled($file), "Expected crop enabled for {$file}");
            $this->assertTrue($fileManager->imageFocusEnabled($file), "Expected focus enabled for {$file}");
        }

        $this->assertFalse($fileManager->imageCropEnabled('logo.svg'));
        $this->assertFalse($fileManager->imageFocusEnabled('logo.svg'));
    }

    public function test_false_disables_regardless_of_file_type(): void
    {
        config(['leap.filemanager.image_crop_enabled' => false]);
        config(['leap.filemanager.image_focus_enabled' => false]);

        $fileManager = new FileManager;

        $this->assertFalse($fileManager->imageCropEnabled('photo.jpg'));
        $this->assertFalse($fileManager->imageFocusEnabled('photo.jpg'));
    }

    public function test_array_form_still_allows_excluding_gif_from_crop_only(): void
    {
        // Cropping breaks GIF animation, setting a focus point doesn't — a host can
        // still express that distinction with the array form.
        config(['leap.filemanager.image_crop_enabled' => ['jpeg', 'jpg', 'png', 'webp']]);
        config(['leap.filemanager.image_focus_enabled' => ['jpeg', 'jpg', 'png', 'webp', 'gif']]);

        $fileManager = new FileManager;

        $this->assertFalse($fileManager->imageCropEnabled('photo.gif'));
        $this->assertTrue($fileManager->imageFocusEnabled('photo.gif'));
        $this->assertTrue($fileManager->imageCropEnabled('photo.png'));
        $this->assertTrue($fileManager->imageFocusEnabled('photo.png'));
    }

    public function test_cropping_over_the_original_updates_everything_the_row_knows_about_it(): void
    {
        Storage::fake('public');
        $gd = imagecreatetruecolor(400, 200);
        ob_start();
        imagepng($gd);
        Storage::disk('public')->put('photo.png', ob_get_clean());

        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password' => 'x']));
        Leap::context()->setModule(FileManager::class)->setPermissions([
            FileManager::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);

        $media = Media::forFile('photo.png');
        $sha256 = $media->sha256;
        $this->assertSame(['width' => 400, 'height' => 200], $media->dimensions());

        $fileManager = Livewire::test(FileManager::class)->instance();
        $fileManager->selectedFiles = ['photo.png'];
        $fileManager->cropImage(0, 0, 50, 50, false, null);

        // The cached dimensions have to follow the crop, or the frontend keeps
        // reserving the shape of the picture as it was; and the hash has to
        // follow it too, or every resized copy keeps its old address.
        $media->refresh();
        $this->assertSame(['width' => 200, 'height' => 100], $media->dimensions());
        $this->assertNotSame($sha256, $media->sha256);
    }
}
