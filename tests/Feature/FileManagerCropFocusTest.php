<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Livewire\FileManager;
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
}
