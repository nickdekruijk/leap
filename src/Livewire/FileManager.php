<?php

namespace NickDeKruijk\Leap\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Module;

class FileManager extends Module
{
    public $component = 'leap.filemanager';
    public $icon = 'fas-folder-tree'; // fas-file-alt far-copy fas-folder-tree
    public $priority = 3;
    public $default_permissions = ['read'];
    public $slug = 'filemanager';

    public array $directories = [];
    public array $openFolders = [];
    public array $selectedFiles = [];

    public function __construct()
    {
        $this->title = __('File_manager');
    }

    function humanFileSize($bytes, $dec = 1): string
    {
        $size = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        if ($factor == 0) {
            $dec = 0;
        }
        return sprintf("%.{$dec}f %s", $bytes / (1024 ** $factor), $size[$factor]);
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
     * Get all the folders and files for the directory
     *
     * @param string|null $directory The directory to look in, null for filesystem root
     * @return array An array of folders and files
     */
    private function getFiles(string $directory = null): array
    {
        $folders = [];
        $entries = $this->getStorage()->directories($directory);
        Leap::sort($entries);
        foreach ($entries as $folder) {
            $size = 0;
            $files = $this->getStorage()->allFiles($folder);
            foreach ($files as $file) {
                $size += $this->getStorage()->size($file);
            }
            if (!str_starts_with(basename($folder), '.')) {
                $folders[basename($folder)] = [
                    'encoded' => rawurlencode($folder),
                    'name' => basename($folder),
                    'size' => $this->humanFileSize($size),
                ];
            }
        };
        $files = [];
        $entries = $this->getStorage()->files($directory);
        Leap::sort($entries);
        foreach ($entries as $file) {
            if (!str_starts_with(basename($file), '.')) {
                $files[] = [
                    'encoded' => rawurlencode($file),
                    'name' => basename($file),
                    'size' => $this->humanFileSize($this->getStorage()->size($file)),
                ];
            }
        };
        return ['files' => $files, 'folders' => $folders];
    }

    public function fileIcon($file)
    {
        // 'thumbnail' => $this->isImage($file) ? $this->getStorage()->url($file) : false,
        if ($this->isPdf(rawurldecode($file['encoded']))) {
            return 'far-file-pdf';
        }
        if ($this->isImage(rawurldecode($file['encoded']))) {
            return 'far-file-image';
        }
        return 'far-file';
    }

    public function openDirectory(string $directory, int $depth)
    {
        Gate::authorize('leap::read');
        $this->selectedFiles = [];
        if (isset($this->openFolders[$depth]) && $this->openFolders[$depth] == $directory) {
            $this->closeDirectory($depth - 1);
        } else {
            $this->directories[$depth] = $this->getFiles(rawurldecode($directory));
            $this->openFolders[$depth] = $directory;
            $this->closeDirectory($depth);
        }
        $this->dispatch('update');
    }

    public function closeDirectory($depth)
    {
        foreach ($this->directories as $d => $folder) {
            if ($d > $depth) {
                unset($this->directories[$d]);
                unset($this->openFolders[$d]);
                $this->selectedFiles = [];
            }
        }
    }

    public function currentDirectory(int $depth = null): string
    {
        $folders = explode('/', rawurldecode(end($this->openFolders)));
        if ($depth === null) {
            $depth = count($folders) - 1;
        }
        return $folders[$depth] ?? null ?: $this->getTitle();
    }

    public function createDirectory($depth, ?string $folder)
    {
        Gate::authorize('leap::create');
        if ($folder) {
            $full = rawurldecode($this->openFolders[$depth] ?? '') . '/' . $folder;
            $this->directories[$depth]['folders'][$folder] = [
                'encoded' => rawurlencode($full),
                'name' => $folder,
                'size' => $this->humanFileSize(0),
            ];
            $this->getStorage()->makeDirectory($full);
            Leap::ksort($this->directories[$depth]['folders']);
            $this->dispatch('toast', __('Folder') . ' ' . $folder . ' ' . __('created'))->to(Toasts::class);
            $this->log('create', 'Folder ' . $full);
        }
    }

    public function selectFile($encodedFile = null, $multiple = false, $shiftKey = false)
    {
        $depth = count(explode('/', rawurldecode($encodedFile))) - 1;
        $previousDepth = count(explode('/', rawurldecode(reset($this->selectedFiles)))) - 1;
        if ($encodedFile && ($depth !== $previousDepth || !$this->selectedFiles)) {
            // Close folders below selected file when a file is selected
            $this->closeDirectory($depth);
            $this->selectedFiles = [];
        }
        if ($shiftKey && $this->selectedFiles) {
            // If shift key is pressed, select files between the first currently selected file and the clicked file
            $selecting = false;
            // Start with the first currently selected file
            $this->selectedFiles = [reset($this->selectedFiles)];
            // Add the clicked file if it's different from first
            if (!in_array($encodedFile, $this->selectedFiles)) {
                $this->selectedFiles[] = $encodedFile;
            }
            // Loop through all the files and select the files between the first currently selected file and the clicked file
            foreach ($this->directories[$depth]['files'] as $file) {
                if ($selecting && !in_array($file['encoded'], $this->selectedFiles)) {
                    $this->selectedFiles[] = $file['encoded'];
                }
                if ($encodedFile == $file['encoded']) {
                    $selecting = !$selecting;
                }
                if ($file['encoded'] == reset($this->selectedFiles)) {
                    $selecting = !$selecting;
                }
            }
        } elseif ($multiple) {
            // When alt and/or fn/cmd is pressed, add or remove the file from the selection
            if (count(explode('/', rawurldecode(reset($this->selectedFiles)))) - 1 > $depth) {
                $this->selectedFiles = [];
            }
            if (in_array($encodedFile, $this->selectedFiles)) {
                $this->selectedFiles = array_diff($this->selectedFiles, [$encodedFile]);
            } else {
                $this->selectedFiles[] = $encodedFile;
            }
        } else {
            // Default behavior, select only the clicked file if any
            $this->selectedFiles = $encodedFile ? [$encodedFile] : [];
        }
        usort($this->selectedFiles, function ($a, $b) {
            return collator_compare(collator_create(app()->getLocale()), rawurldecode($a), rawurldecode($b));
        });
    }

    public function selectedFilesStats()
    {
        $size = 0;
        $timeMin = null;
        $timeMax = null;
        $dimensions = null;

        // Calculate total size of all selected files and get the first and last modified date
        foreach ($this->selectedFiles as $file) {
            $size += $this->getStorage()->size(rawurldecode($file));
            $time = $this->getStorage()->lastModified(rawurldecode($file));
            if (count($this->selectedFiles) == 1 && $this->isBitmap(rawurldecode($file))) {
                // When only one file is selected and it's a bitmap image get the dimensions in pixels
                $image = Image::read($this->getStorage()->get(rawurldecode($file)));
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
            $dates = Carbon::createFromTimestamp($timeMin)->isoFormat(__('datetime_format_long'));
        } else {
            $timeMin = Carbon::createFromTimestamp($timeMin)->isoFormat(__('date_format_short'));
            $timeMax = Carbon::createFromTimestamp($timeMax)->isoFormat(__('date_format_short'));
            if ($timeMin == $timeMax) {
                $dates = $timeMin;
            } else {
                $dates = $timeMin . ' - ' . $timeMax;
            }
        }

        return [
            'Size' => $this->humanFileSize($size),
            'date_modified' => $dates,
            'Dimensions' => $dimensions,
        ];
    }

    public function hasExtension(string $file, array|string $extensions): bool
    {
        $extension = strtolower(pathinfo($file)['extension']);
        if (!is_array($extensions)) {
            $extensions = explode(',', $extensions);
        }
        return in_array($extension, $extensions);
    }

    public function isImage(string $file): bool
    {
        return $this->hasExtension($file, ['jpg', 'jpeg', 'png', 'gif', 'svg']);
    }

    public function isBitmap(string $file): bool
    {
        return $this->hasExtension($file, ['jpg', 'jpeg', 'png', 'gif']);
    }

    public function isPdf(string $file): bool
    {
        return $this->hasExtension($file, 'pdf');
    }

    public function mount()
    {
        // Check if the user has read permission to this module
        Gate::authorize('leap::read');

        // Get all directories from disk
        $this->directories[] = $this->getFiles();
    }

    public function render()
    {
        $this->log('read');
        return view('leap::livewire.filemanager')->layout('leap::layouts.app');
    }
}
