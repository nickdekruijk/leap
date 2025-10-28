<?php

namespace NickDeKruijk\Leap\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\WithFileUploads;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Module;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileManager extends Module
{
    use WithFileUploads;

    public array $uploads = [];
    public $chunkSize = 1024 * 1024;

    public $component = 'leap.filemanager';
    public $icon = 'fas-folder-tree'; // fas-file-alt far-copy fas-folder-tree
    public $priority = 50;
    protected $default_permissions = [
        'create' => false,
        'read' => true,
        'update' => false,
        'delete' => false,
    ];
    public $slug = 'filemanager';

    public array $openFolders = [];
    public array $selectedFiles = [];

    public bool $editingFile = false;
    public string|null $newFileName;

    #[Locked]
    public array|false $browse = false;

    public function __construct()
    {
        $this->title = __('leap::filemanager.title');
    }

    #[Computed]
    public function uploading()
    {
        foreach ($this->uploads as $upload) {
            if ($upload['progress'] < 100 && !$upload['error']) {
                return true;
            }
        }
        return false;
    }

    public function uploadStart($id, $name, $size)
    {
        Leap::validatePermission('create');

        $this->uploads[$id] = [
            'name' => $name,
            'size' => $size,
            'progress' => 0,
            'depth' => count($this->openFolders),
            'currentDirectory' => $this->currentDirectory(),
            'path' => implode('/', $this->openFolders),
            'error' => false,
        ];
        if (!$this->hasExtension($name, config('leap.filemanager.allowed_extensions'))) {
            $this->uploads[$id]['error'] = true;
            $this->dispatch('toast-error', __('leap::filemanager.upload_not_allowed', ['attribute' => $name]))->to(Toasts::class);
        }
        if ($size > $this->maxUploadSize()) {
            $this->uploads[$id]['error'] = true;
            $this->dispatch('toast-error', __('leap::filemanager.upload_too_large', ['attribute' => $name]))->to(Toasts::class);
        }
    }

    public function uploadDone($id)
    {
        Leap::validatePermission('create');

        if ($this->uploads[$id]['error']) {
            return;
        }

        $file = $this->uploads[$id];

        // Check if uploaded file already exists
        if ($this->getStorage()->exists($file['path'] . '/' . $file['name'])) {
            // Compare sha256 hash of both files
            $hash_existing = hash('sha256', $this->getStorage()->get($file['path'] . '/' . $file['name']));
            $hash_uploaded = hash_file('sha256', $file['file']->path());
            if ($hash_existing === $hash_uploaded) {
                $this->dispatch('toast-error', __('leap::filemanager.already_exist', ['attribute' => $file['name']]))->to(Toasts::class);
                return;
            }
            $n = 1;
            $fileParts = pathinfo($file['name']);
            while ($this->getStorage()->exists($file['path'] . '/' . $fileParts['filename'] . '-' . $n . '.' . $fileParts['extension'])) {
                $n++;
            }
            $this->dispatch('toast-alert', __('leap::filemanager.already_exist', ['attribute' => $file['name']]))->to(Toasts::class);
            $file['name'] = $fileParts['filename'] . '-' . $n . '.' . $fileParts['extension'];
        }

        if ($file['file']->storeAs($file['path'], $file['name'], config('leap.filemanager.disk'))) {
            Media::forFile($file['path'] . '/' . $file['name']);
            $this->dispatch('toast', __('leap::filemanager.upload_done', ['attribute' => $file['name']]))->to(Toasts::class);
            $this->log('upload', $file['path'] . '/' . $file['name']);
            unset($this->columns);
        } else {
            $this->dispatch('toast-error', __('leap::filemanager.upload_failed', ['attribute' => $file['name']]))->to(Toasts::class);
        }
    }

    public function uploadFailed($id)
    {
        $this->dispatch('toast-error', __('leap::filemanager.upload_failed', ['attribute' => $this->uploads[$id]['name']]))->to(Toasts::class);
    }

    public function uploadClear($id)
    {
        if ($this->uploads[$id]['error']) {
            unset($this->uploads[$id]);
        }
    }

    /**
     * Convert a size string to bytes
     *
     * @param string $value a size string e.g. '1K', '2M', '3G'
     * @return int e.g. 1024, 2097152, 3221225472
     */
    function bytes(string $value): int
    {
        $value = trim($value);
        $num = (int) $value;
        $last = substr($value, -1);

        $factor = [
            'K' => 1,
            'M' => 2,
            'G' => 3,
        ];

        return $num * 1024 ** ($factor[$last] ?? 0);
    }

    /**
     * Return human readable maximum upload filesize
     *
     * @return string
     */
    public function maxUploadSize(): string
    {
        foreach (FileUploadConfiguration::rules() as $rule) {
            $rule = explode(':', $rule);
            if ($rule[0] == 'max') {
                $livewireMax = $rule[1] * 1024;
            }
        }
        return min($livewireMax ?? $this->bytes('12M'), $this->bytes(config('leap.filemanager.upload_max_filesize')), $this->bytes(ini_get('upload_max_filesize')), $this->bytes(ini_get('post_max_size')));
    }

    /**
     * Convert a number into human readable format
     *
     * @param int $bytes
     * @param integer decimals
     * @return string
     */
    function humanFileSize(int $bytes, int $decimals = 1): string
    {
        $size = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        if ($factor == 0) {
            $decimals = 0;
        }
        return sprintf("%.{$decimals}f %s", $bytes / (1024 ** $factor), $size[$factor]);
    }

    /**
     * Get the disk storage from Laravel filesystems configuration
     *
     * @return Storage|Filesystem
     */
    private function getStorage(): Filesystem
    {
        return Storage::disk(config('leap.filemanager.disk'));
    }

    /**
     * Return all filemanager columns with folders and files
     *
     * @return array
     */
    #[Computed(persist: true)]
    public function columns(): array
    {
        $columns = [$this->getFiles()];
        $path = '';
        foreach ($this->openFolders as $folder) {
            $path .= $folder;
            $columns[] = $this->getFiles($path);
            $path .= '/';
        }
        return $columns;
    }

    /**
     * Get all the folders and files for the directory
     *
     * @param string|null $directory The directory to look in, null for filesystem root
     * @return array An array of folders and files
     */
    private function getFiles(null|string $directory = null): array
    {
        $folders = [];
        $entries = $this->getStorage()->directories($directory);
        Leap::basenamesort($entries);
        foreach ($entries as $folder) {
            $size = 0;
            $files = $this->getStorage()->allFiles($folder);
            foreach ($files as $file) {
                $size += $this->getStorage()->size($file);
            }
            if (!str_starts_with(basename($folder), '.')) {
                $folders[basename($folder)] = $this->humanFileSize($size);
            }
        };
        $files = [];
        $entries = $this->getStorage()->files($directory);
        Leap::basenamesort($entries);
        foreach ($entries as $file) {
            if (!str_starts_with(basename($file), '.')) {
                $files[basename($file)] = $this->humanFileSize($this->getStorage()->size($file));
            }
        };
        return ['files' => $files, 'folders' => $folders];
    }

    public function fileIcon($name)
    {
        if ($this->isPdf($name)) {
            return 'far-file-pdf';
        }
        if ($this->isImage($name)) {
            return 'far-file-image';
        }
        return 'far-file';
    }

    public function openDirectory(string $encodedName, int $depth)
    {
        $name = urldecode($encodedName);
        $this->selectedFiles = [];

        if (isset($this->openFolders[$depth]) && $this->openFolders[$depth] == $name) {
            $this->closeDirectory($depth - 1);
        } else {
            $this->openFolders[$depth] = $name;
            $this->closeDirectory($depth);
        }
    }

    public function closeDirectory($depth)
    {
        foreach ($this->openFolders as $d => $folder) {
            if ($d > $depth) {
                unset($this->openFolders[$d]);
                $this->selectedFiles = [];
            }
        }

        // Bust the columns cache
        unset($this->columns);
    }

    public function currentDirectory(null|int $depth = null): string
    {
        if ($depth === null) {
            $depth = count($this->openFolders);
        }
        return ($this->openFolders[$depth] ?? null) ?: $this->getTitle();
    }

    public function createDirectory($depth, ?string $folder)
    {
        Leap::validatePermission('create');
        if ($folder) {
            $this->closeDirectory($depth);
            $full = $this->full($folder);
            // Check if folder contains invalid characters
            if (str_starts_with($folder, '.') || preg_match('/[\/\\\]/', $folder)) {
                $this->dispatch('toast-error', __('leap::filemanager.invalid_characters', ['attribute' => $folder]))->to(Toasts::class);
                return false;
            }
            // Check if the directory already exists, toast error if it doesn't
            if ($this->getStorage()->exists($full)) {
                $this->dispatch('toast-error', __('leap::filemanager.already_exist', ['attribute' => $full]))->to(Toasts::class);
                return false;
            }
            if ($this->getStorage()->makeDirectory($full)) {
                $this->dispatch('toast', __('leap::filemanager.created_folder', ['attribute' => $folder]))->to(Toasts::class);
                $this->log('create', 'Folder ' . $full);
            } else {
                $this->dispatch('toast-error', __('leap::filemanager.create_folder_failed', ['attribute' => $folder]))->to(Toasts::class);
            }
        }
    }

    /**
     * Delete all selected files
     *
     * @return void
     */
    public function deleteFiles()
    {
        Leap::validatePermission('delete');
        foreach ($this->selectedFiles as $id => $file) {
            $full = $this->full($file);
            $media = Media::findFile($full);
            if ($media) {
                if ($media->mediables->count()) {
                    $this->dispatch('toast-error', __('leap::filemanager.media_in_use', ['attribute' => $file]))->to(Toasts::class);
                    continue;
                } else {
                    $media->delete();
                }
            }
            $delete = $this->getStorage()->delete($full);
            if ($delete) {
                $this->dispatch('toast', __('leap::filemanager.deleted_file', ['attribute' => $file]))->to(Toasts::class);
                $this->log('delete', 'File ' . $full);
                unset($this->selectedFiles[$id]);
            } else {
                $this->dispatch('toast-error', __('leap::filemanager.deleted_file_error', ['attribute' => $file]))->to(Toasts::class);
            }
        }
        unset($this->columns);
    }

    /**
     * Delete the directory at the given depth
     *
     * @param integer $depth
     * @return boolean true if the directory was deleted, false if not
     */
    public function deleteDirectory(int $depth): bool
    {
        Leap::validatePermission('delete');

        $this->closeDirectory($depth);
        $full = implode('/', $this->openFolders);

        // Check if the directory exists and is in the columns array, toast error if it doesn't
        if (!$this->getStorage()->exists($full)) {
            $this->dispatch('toast-error', __('leap::filemanager.does_not_exist', ['attribute' => $full]))->to(Toasts::class);
            return false;
        }

        // Check if the directory is empty, toast error if it doesn't
        if ($this->getStorage()->allFiles($full) || $this->getStorage()->allDirectories($full)) {
            $this->dispatch('toast-error', __('leap::filemanager.is_not_empty', ['attribute' => $full]))->to(Toasts::class);
            return false;
        }

        // Delete the directory and toast on success or error
        $delete = $this->getStorage()->deleteDirectory($full);
        if ($delete) {
            $this->dispatch('toast', __('leap::filemanager.deleted_folder', ['attribute' => $this->currentDirectory()]))->to(Toasts::class);
            $this->log('delete', 'Folder ' . $full);
            $this->closeDirectory($depth - 1);
        } else {
            $this->dispatch('toast-error', __('leap::filemanager.deleted_folder_error', ['attribute' => $this->currentDirectory()]))->to(Toasts::class);
        }
        return $delete;
    }

    public function selectBrowsedFiles()
    {
        $full = [];
        foreach ($this->selectedFiles as $id => $file) {
            $full[] = $this->full($file);
        }
        if ($this->browse['attribute'] === '_tinymce') {
            // Get full url of first file
            $file = $this->getStorage()->url($full[0]);
            // Strip current domain from url
            $file = str_replace(url('/'), '', $file);
            $this->dispatch('tinymceBrowser', $file);
        } elseif (isset($this->browse['media'])) {
            $this->dispatch('selectMediaFiles', $this->browse['attribute'], $full);
        } else {
            $this->dispatch('selectBrowsedFiles', $this->browse['attribute'], $full);
        }
    }

    public function selectFile($encodedFileName = null, $depth = null, $multiple = false, $shiftKey = false)
    {
        // Reset edit mode
        $this->editFile(true);

        // Don't select multiple files if only one is allowed while browsing
        if ($this->browse && !$this->browse['multiple'] && ($multiple || $shiftKey)) {
            return;
        }

        // Url decode the name as it's encoded by the blade template
        $fileName = urldecode($encodedFileName);

        // Close folders if new selected file is in different folder
        if ($fileName && ($depth !== count($this->openFolders) || !$this->selectedFiles)) {
            // dd($depth, $this->openFolders);
            $this->closeDirectory($depth);
            $this->selectedFiles = [];
        }

        if ($shiftKey && $this->selectedFiles) {
            // If shift key is pressed, select files between the first currently selected file and the clicked file
            $selecting = false;
            // Start with the first currently selected file
            $this->selectedFiles = [reset($this->selectedFiles)];
            // Add the clicked file if it's different from first
            if (!in_array($fileName, $this->selectedFiles)) {
                $this->selectedFiles[] = $fileName;
            }
            // Loop through all the files and select the files between the first currently selected file and the clicked file
            foreach ($this->columns[$depth]['files'] as $name => $size) {
                if ($selecting && !in_array($name, $this->selectedFiles)) {
                    $this->selectedFiles[] = $name;
                }
                if ($fileName == $name) {
                    $selecting = !$selecting;
                }
                if ($name    == reset($this->selectedFiles)) {
                    $selecting = !$selecting;
                }
            }
        } elseif ($multiple) {
            // When alt and/or fn/cmd is pressed, unselect or select the clicked file
            if (in_array($fileName, $this->selectedFiles)) {
                $this->selectedFiles = array_diff($this->selectedFiles, [$fileName]);
            } else {
                $this->selectedFiles[] = $fileName;
            }
        } else {
            // Default behavior, select only the clicked file if any
            $this->selectedFiles = $fileName ? [$fileName] : [];
        }
        Leap::sort($this->selectedFiles);
    }

    /**
     * Add the full path in front of a file or folder name
     *
     * @param string $name the file or foldername
     * @param boolean $urlencode specify if the full path should be urlencoded
     * @return string
     */
    public function full(string $name, bool $urlencode = false): string
    {
        // Add open folders to the full path if any
        if ($this->openFolders) {
            $full = implode('/', $this->openFolders) . '/' . $name;
        } else {
            $full = $name;
        }

        // rawurlencode the full path but not forward slashes
        if ($urlencode) {
            $full = rawurlencode($full);
            $full = str_replace('%2F', '/', $full);
        }

        return $full;
    }

    /**
     * Add the full path in front of a file or folder name and urlencode it
     *
     * @param string $name the file or foldername
     * @return string
     */
    public function encode(string $name): string
    {
        return $this->full($name, true);
    }

    /**
     * Generate an url to download a file
     *
     * @param string $file the file including full path
     * @return string the full url
     */
    public function downloadUrl(string $file): string
    {
        return route(
            'leap.module.' . $this->getSlug() . '.download',
            [
                'name' => $this->encode($file),
                'organization' => Context::getHidden('leap.organization.slug'),
            ]
        );
    }

    /**
     * Return the file from storage as a response
     *
     * @param string $file the file including full path
     * @param string|null $organization the organization slug if applicable
     * @return StreamedResponse
     */
    public function download(string $file, ?string $organization = null): StreamedResponse
    {
        // Since this is used as a direct route the Module boot method should be called, to set module context and check read permissions
        parent::boot();

        // Check if the file exists
        abort_if(!$this->getStorage()->exists($file), 404);

        // Return the file as a response to not force downloads in browser
        return $this->getStorage()->response($file);
    }

    public function downloadForOrganization(string $organization, string $file): StreamedResponse
    {
        return $this->download($file, $organization);
    }

    public function editFile($close = false)
    {
        if ($close) {
            $this->editingFile = false;
            $this->newFileName = null;
        } else {
            $this->editingFile = true;
            $this->newFileName = reset($this->selectedFiles);
        }
    }

    public function renameSelectedFile()
    {
        Leap::validatePermission('update');

        // Don't allow multiple slashes, starting with a slash, containing /. or starting with a dot except for ../ if inside folder
        if (
            substr_count($this->newFileName, '/') > 1
            || str_starts_with($this->newFileName, '/')
            || str_contains($this->newFileName, '/.')
            || (str_starts_with($this->newFileName, '.') && !str_starts_with($this->newFileName, '../'))
            || (str_starts_with($this->newFileName, '../') && count($this->openFolders) == 0)
        ) {
            $this->dispatch('toast-error', __('leap::filemanager.rename_invalid_path', ['attribute' => $this->newFileName]))->to(Toasts::class);
            return;
        }

        // Get from and to file with full path
        $from = $this->full(reset($this->selectedFiles));
        $to = $this->full($this->newFileName);

        // Check if new file exists
        if ($this->getStorage()->exists($to)) {
            $this->dispatch('toast-error', __('leap::filemanager.already_exist', ['attribute' => $this->newFileName]))->to(Toasts::class);
            return;
        }

        // Only rename if the file names are different
        if ($from != $to) {
            // Move or rename the file inside storage
            $this->getStorage()->move($from, $to);

            if (str_starts_with($this->newFileName, '../')) {
                // Moving to parent folder, strip ../ from the beginning and close directory
                $this->newFileName = substr($this->newFileName, 3);
                $this->closeDirectory(count($this->openFolders) - 1);
            } elseif (str_contains($this->newFileName, '/')) {
                // Moving to new folder, only return the part after the first slash and open new directory
                $this->newFileName = substr($this->newFileName, strpos($this->newFileName, '/') + 1);
                $this->openDirectory(urlencode(substr($this->newFileName, 0, strpos($this->newFileName, '/'))), count($this->openFolders) + 1);
            }

            // Set new selected file
            $this->selectedFiles = [$this->newFileName];

            // Update media entry and history if present
            if ($media = Media::findFile($from)) {
                $media->file_name = $to;
                $history = $media->history;
                $history[] = now() . ' Renamed from ' . $from . ' to ' . $to . ' by ' . Auth::user()?->name . ' #' . Auth::user()?->id;
                $media->history = $history;
                $media->save();
            }

            // Show success message
            $this->dispatch('toast', __('leap::filemanager.rename_success', ['attribute' => $this->newFileName]))->to(Toasts::class);

            // Force refresh
            unset($this->columns);
        }

        // Close file rename input
        $this->editFile(true);
    }

    public function selectedFilesStats()
    {
        $size = 0;
        $timeMin = null;
        $timeMax = null;
        $dimensions = null;

        // Calculate total size of all selected files and get the first and last modified date
        foreach ($this->selectedFiles as $file) {
            $full = $this->full($file);
            $size += $this->getStorage()->size($full);
            $time = $this->getStorage()->lastModified($full);
            if (count($this->selectedFiles) == 1 && $this->isBitmap($full)) {
                // When only one file is selected and it's a bitmap image get the dimensions in pixels
                $image = Image::read($this->getStorage()->get($full));
                $dimensions = $image->width() . ' x ' . $image->height();
            }
            if ($timeMin === null || $time < $timeMin) {
                $timeMin = $time;
            }
            if ($timeMax === null || $time > $timeMax) {
                $timeMax = $time;
            }
        }

        // Format the dates for display
        if (count($this->selectedFiles) == 1) {
            $dates = Carbon::createFromTimestamp($timeMin)->isoFormat(__('leap::filemanager.datetime_format_long'));
        } else {
            $timeMin = Carbon::createFromTimestamp($timeMin)->isoFormat(__('leap::filemanager.date_format_short'));
            $timeMax = Carbon::createFromTimestamp($timeMax)->isoFormat(__('leap::filemanager.date_format_short'));
            if ($timeMin == $timeMax) {
                $dates = $timeMin;
            } else {
                $dates = $timeMin . ' - ' . $timeMax;
            }
        }

        return [
            'size' => $this->humanFileSize($size),
            'date_modified' => $dates,
            'dimensions' => $dimensions,
        ];
    }

    public function hasExtension(string $file, array|string $extensions): bool
    {
        $extension = strtolower(pathinfo($file)['extension'] ?? null);
        if (!is_array($extensions)) {
            $extensions = explode(',', $extensions);
        }
        return in_array($extension, $extensions);
    }

    public function isAudio(string $file): bool
    {
        return $this->hasExtension($file, ['flac', 'mp3', 'wav', 'aac']);
    }
    public function isVideo(string $file): bool
    {
        return $this->hasExtension($file, ['mp4', 'm4v', 'mov', 'avi', 'wmv']);
    }

    public function isImage(string $file): bool
    {
        return $this->hasExtension($file, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']);
    }

    public function isBitmap(string $file): bool
    {
        return $this->hasExtension($file, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    public function isPdf(string $file): bool
    {
        return $this->hasExtension($file, 'pdf');
    }

    public function render()
    {
        $this->log('read');
        /** @disregard P1013 Undefined method intelephense error */
        return view('leap::livewire.filemanager')->layout('leap::layouts.app');
    }
}
