<main class="leap-main leap-filemanager" x-data="{
    uploadFiles: function(el) {
        const uploadStart = new Date().getTime();
        [...el.files].forEach((file, index) => {
            $wire.uploadStart(uploadStart + index, file.name, file.size);
            if (file.size <= @js($this->maxUploadSize())) {
                $wire.upload('uploads.' + (uploadStart + index) + '.file', file, () => {
                    $wire.uploadDone(uploadStart + index);
                }, () => {
                    $wire.uploadFailed(uploadStart + index);
                }, (event) => {
                    $wire.set('uploads.' + (uploadStart + index) + '.progress', event.detail.progress);
                }, () => {
                    // Cancelled callback...
                });
            }
        });
    }
}">
    <header class="leap-header">
        <h2>{{ $this->currentDirectory() }}</h2>
        @if ($browse)
            <x-leap::button svg-icon="fas-times" wire:click="$parent.fileBrowser" label="leap::resource.cancel" />
        @endif
    </header>
    @can('leap::create')
        <form>
            <input type="file" tabindex="-1" multiple id="leap-filemanager-upload" x-on:change="uploadFiles($el);$el.parentNode.reset()">
        </form>
    @endcan
    <div class="leap-index"
        @can('leap::create')
            x-data="{ dropping: false }"
            x-bind:class="{ 'leap-index-dropzone': dropping }"
            x-on:dragover.prevent="dropping = true"
            x-on:dragleave.prevent="dropping = false"
            x-on:drop.prevent="dropping = false; uploadFiles($event.dataTransfer)"
        @endcan>
        @foreach ($this->columns as $depth => $directory)
            <table class="leap-index-table">
                <tr class="leap-index-header">
                    <th colspan="2">
                        <div class="leap-buttons">
                            @if ($depth < count($openFolders))
                                {{ $this->currentDirectory($depth) }}
                            @else
                                @can('leap::create')
                                    <button x-on:click="$wire.createDirectory({{ $depth }},prompt('@lang('leap::filemanager.new_folder')'))" class="leap-button">@svg('fas-folder-plus', 'svg-icon')<span> @lang('leap::filemanager.new_folder')</span></button>
                                    <button x-on:click="document.getElementById('leap-filemanager-upload').click()" @if ($this->uploading) disabled @endif class="leap-button">
                                        @svg('fas-upload', 'svg-icon')
                                        <span>@lang('Upload') <small>(max {{ $this->humanFileSize($this->maxUploadSize(), 0) }})</small></span>
                                    </button>
                                @endcan
                            @endif
                            @if ($depth > 0)
                                @can('leap::delete')
                                    @if (count($directory['folders']) == 0 && count($directory['files']) == 0)
                                        <button
                                            wire:click="deleteDirectory({{ $depth }})"
                                            wire:confirm="@lang('leap::filemanager.delete_folder_confirm')"
                                            class="leap-button secondary">
                                            @svg('fas-trash-alt', 'svg-icon')<span> @lang('leap::filemanager.delete_folder')</span>
                                        </button>
                                    @endcan
                                @endif
                            @endif
                        </div>
                    </th>
                </tr>
                @foreach ($uploads as $id => $upload)
                    @if ($upload['depth'] == $depth && $upload['currentDirectory'] == $this->currentDirectory($depth))
                        <tr wire:click="uploadClear({{ $id }})" class="leap-index-row leap-filemanager-uploading @if ($upload['progress'] >= 100) leap-filemanager-uploading-done @endif @if ($upload['error']) leap-filemanager-uploading-error @endif">
                            <td>
                                @svg('fas-upload', 'svg-icon') {{ $upload['name'] }}
                                <progress value="{{ $upload['progress'] }}" max="100">Test</progress>
                            </td>
                            <td align="right">
                                {{ $this->humanFileSize($upload['size']) }}
                            </td>
                        </tr>
                    @endif
                @endforeach
                @if ($depth > 0)
                    <tr wire:click="closeDirectory({{ $depth - 1 }})" class="leap-index-row leap-filemanager-parent">
                        <td><button class="button-link">@svg('fas-arrow-left', 'svg-icon') <em>{{ $this->currentDirectory($depth - 2) }}</em></button></td>
                        <td></td>
                    </tr>
                @endif
                @foreach ($directory['folders'] as $name => $size)
                    <tr wire:click="openDirectory('{{ urlencode($name) }}',{{ $depth + 1 }})" class="leap-index-row @if (@$openFolders[$depth + 1] == $name) leap-index-row-selected @endif">
                        <td><button class="button-link">@svg('fas-folder' . ($openFolders[$depth] ?? false == $name ? '-open' : ''), 'svg-icon') {{ $name }}</button></td>
                        <td align="right">{{ $size }}</td>
                    </tr>
                @endforeach
                @foreach ($directory['files'] as $name => $size)
                    <tr x-on:click="$wire.selectFile('{{ urlencode($name) }}',{{ $depth }},window.event.altKey||window.event.metaKey,window.event.shiftKey)" @if ($browse) wire:dblclick="selectBrowsedFiles" @endif class="leap-index-row @if ($depth == count($openFolders) && in_array($name, $selectedFiles)) leap-index-row-selected @endif">
                        <td>
                            <button class="button-link">
                                @svg($this->fileIcon($name), 'svg-icon')
                                {{ $name }}
                            </button>
                        </td>
                        <td align="right">{{ $size }}</td>
                    </tr>
                @endforeach
            </table>
        @endforeach
        @if ($selectedFiles)
            <div class="leap-filemanager-selected">
                <div class="leap-buttons" role="group" x-on:keydown.escape.window="$wire.selectFile">
                    @if ($browse && $selectedFiles)
                        <button
                            wire:click="selectBrowsedFiles"
                            class="leap-button primary">
                            @svg('far-check-circle', 'leap-svg-icon')<span> @lang('leap::filemanager.select_file' . (count($selectedFiles) > 1 ? 's' : ''), ['count' => count($selectedFiles)])</span>
                        </button>
                    @endif
                    <x-leap::button svg-icon="fas-xmark" x-on:click="$wire.selectFile" label="leap::filemanager.close" />
                    @can('leap::delete')
                        <button
                            wire:click="deleteFiles"
                            wire:confirm="@lang('leap::filemanager.delete_file' . (count($selectedFiles) > 1 ? 's' : '') . '_confirm')"
                            class="leap-button secondary">
                            @svg('fas-trash-alt', 'leap-svg-icon')<span> @lang('leap::filemanager.delete_file' . (count($selectedFiles) > 1 ? 's' : ''))</span>
                        </button>
                    @endcan
                </div>
                <div class="leap-filemanager-preview">
                    <div class="leap-filemanager-preview-items" style="grid-template-columns: repeat({{ ceil(sqrt(count($selectedFiles))) }}, 1fr)">
                        @foreach ($selectedFiles as $file)
                            <div class="leap-filemanager-preview-item">
                                @if ($this->isImage($file))
                                    <img src="{{ $this->downloadUrl($file) }}" alt="">
                                @endif
                                @if ($this->isVideo($file))
                                    <video controls src="{{ $this->downloadUrl($file) }}"></video>
                                @endif
                                @if ($this->isAudio($file))
                                    <audio controls src="{{ $this->downloadUrl($file) }}"></audio>
                                @endif
                                <a href="{{ $this->downloadUrl($file) }}" target="_blank" rel="noopener">
                                    <span>@svg('fas-external-link-alt', 'svg-icon') {{ $file }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="leap-filemanager-stats">
                    <h3>
                        @if (count($selectedFiles) > 1)
                            {{ count($selectedFiles) }} @lang('leap::filemanager.files')
                        @else
                            {{ reset($selectedFiles) }}
                        @endif
                    </h3>
                    <table>
                        @foreach ($this->selectedFilesStats() as $key => $value)
                            @if ($value)
                                <tr>
                                    <td>@lang('leap::filemanager.' . $key)</td>
                                    <td align="right">{{ $value }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
                </div>
            </div>
        @endif
    </div>
    @script
        <script>
            Livewire.hook('morphed', () => {
                let e = document.querySelector('.leap-filemanager .leap-index');
                if (e) {
                    e.scrollTo({
                        left: e.scrollWidth,
                        behavior: 'smooth'
                    });
                }
            });
        </script>
    @endscript
</main>
