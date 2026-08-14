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
 * leap.filemanager.slug_uploads: the name a new file gets.
 *
 * Encoding on the way out repairs the files that are already there; this keeps
 * the next space or comma from getting in at all.
 */
class FileManagerSlugUploadsTest extends TestCase
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

    private function upload(string $name, int $width = 10): FileManager
    {
        $fileManager = Livewire::test(FileManager::class)->instance();
        $fileManager->uploads = ['x' => [
            'name' => $name,
            'path' => '',
            'error' => false,
            'file' => UploadedFile::fake()->image($name, $width, 10),
        ]];

        $fileManager->uploadDone('x');

        return $fileManager;
    }

    public function test_an_uploaded_file_is_stored_under_a_slugged_name(): void
    {
        $this->upload('Foto met spatie, komma.jpg');

        Storage::disk('public')->assertExists('foto-met-spatie-komma.jpg');
        Storage::disk('public')->assertMissing('Foto met spatie, komma.jpg');

        // And the row that every page reaches the file through knows it by that
        // name too, or the file is there and nothing can find it.
        $this->assertNotNull(Media::findFile('foto-met-spatie-komma.jpg'));
    }

    public function test_the_numbered_copy_counts_the_name_the_file_is_stored_under(): void
    {
        // Different contents, or the second upload is recognised as the same
        // file and never reaches the numbering at all.
        $this->upload('Foto met spatie.jpg', 10);
        $this->upload('Foto met spatie.jpg', 20);

        Storage::disk('public')->assertExists('foto-met-spatie.jpg');
        Storage::disk('public')->assertExists('foto-met-spatie-1.jpg');
    }

    public function test_a_host_can_keep_the_original_names(): void
    {
        config(['leap.filemanager.slug_uploads' => false]);

        $this->upload('Foto met spatie, komma.jpg');

        Storage::disk('public')->assertExists('Foto met spatie, komma.jpg');
    }

    /**
     * A name written entirely in a script Str::slug drops is kept as it is: a
     * file with an awkward name beats a file called "-.jpg", and the encoding
     * on the way out covers it either way.
     */
    public function test_a_name_that_slugs_away_to_nothing_is_left_alone(): void
    {
        $this->upload('日本.jpg');

        Storage::disk('public')->assertExists('日本.jpg');
    }

    public function test_renaming_slugs_the_new_name(): void
    {
        Storage::disk('public')->put('ok.jpg', 'x');

        $fileManager = Livewire::test(FileManager::class)->instance();
        $fileManager->selectedFiles = ['ok.jpg'];
        $fileManager->newFileName = 'Nieuwe naam, met komma.jpg';
        $fileManager->renameSelectedFile();

        Storage::disk('public')->assertExists('nieuwe-naam-met-komma.jpg');
        Storage::disk('public')->assertMissing('ok.jpg');
    }

    /**
     * Only the part after the last slash: the "../" a rename uses to move a file
     * up a level is path, not name, and slugging it would turn the move into a
     * file called "-".
     */
    public function test_renaming_into_the_parent_folder_still_works(): void
    {
        Storage::disk('public')->put('map/ok.jpg', 'x');

        $fileManager = Livewire::test(FileManager::class)->instance();
        $fileManager->openFolders = ['map'];
        $fileManager->selectedFiles = ['ok.jpg'];
        $fileManager->newFileName = '../Twee woorden.jpg';
        $fileManager->renameSelectedFile();

        Storage::disk('public')->assertExists('twee-woorden.jpg');
        Storage::disk('public')->assertMissing('map/ok.jpg');
    }

    public function test_cropping_to_a_new_file_slugs_its_name(): void
    {
        $gd = imagecreatetruecolor(400, 200);
        ob_start();
        imagepng($gd);
        Storage::disk('public')->put('photo.png', ob_get_clean());

        $fileManager = Livewire::test(FileManager::class)->instance();
        $fileManager->selectedFiles = ['photo.png'];
        $fileManager->cropImage(0, 0, 50, 50, true, 'Bijgesneden foto, groot.png');

        Storage::disk('public')->assertExists('bijgesneden-foto-groot.png');
    }
}
