<?php

namespace NickDeKruijk\Leap\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        sort($entries, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($entries as $folder) {
            $size = 0;
            $files = $this->getStorage()->allFiles($folder);
            foreach ($files as $file) {
                $size += $this->getStorage()->size($file);
            }
            if (!str_starts_with(basename($folder), '.')) {
                $folders[basename($folder)] = (object)[
                    'encoded' => rawurlencode($folder),
                    'name' => basename($folder),
                    'size' => $this->humanFileSize($size),
                ];
            }
        };
        $files = [];
        $entries = $this->getStorage()->files($directory);
        sort($entries, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($entries as $file) {
            if (!str_starts_with(basename($file), '.')) {
                $files[] = (object)[
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
        return $this->isImage(rawurldecode($file->encoded)) ? 'far-file-image' : 'far-file';
    }

    public function openDirectory(string $directory, int $depth)
    {
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
            }
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
                if ($selecting && !in_array($file->encoded, $this->selectedFiles)) {
                    $this->selectedFiles[] = $file->encoded;
                }
                if ($encodedFile == $file->encoded) {
                    $selecting = !$selecting;
                }
                if ($file->encoded == reset($this->selectedFiles)) {
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
    }

    public function selectedFilesStats()
    {
        $size = 0;
        $timeMin = null;
        $timeMax = null;

        // Calculate total size of all selected files and get the first and last modified date
        foreach ($this->selectedFiles as $file) {
            $size += $this->getStorage()->size(rawurldecode($file));
            $time = $this->getStorage()->lastModified(rawurldecode($file));
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

        return (object)[
            'Size' => $this->humanFileSize($size),
            'date_modified' => $dates,
        ];
    }

    public function isImage($file)
    {
        return
            $this->getStorage()->mimeType($file) == 'image/jpeg'
            || $this->getStorage()->mimeType($file) == 'image/png'
            || $this->getStorage()->mimeType($file) == 'image/gif'
            || $this->getStorage()->mimeType($file) == 'image/svg+xml';
    }

    public function getPreview($encodedFile)
    {
        $file = rawurldecode($encodedFile);
        $preview = '';

        if ($this->getStorage()->exists($file)) {
            // Check if the file is an image
            if ($this->isImage($file)) {
                $preview .= '<img src="' . $this->getStorage()->url(rawurlencode($file)) . '" alt="' . basename($file) . '">';
            }
            $preview .= '<a href="' . $this->getStorage()->url(rawurlencode($file)) . '" target="_blank" rel="noopener">';
            $preview .= '<span>' . svg('fas-external-link-alt', 'svg-icon')->toHtml() . basename($file) . '</span>';
            $preview .= '</a>';
            return $preview;
        }
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
