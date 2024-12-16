<main class="leap-main leap-filemanager">
    <header class="leap-header">
        <h2>{{ $this->currentDirectory() }}</h2>
    </header>
    <div class="leap-index">
        @foreach ($this->columns as $depth => $directory)
            <table class="leap-index-table">
                <tr class="leap-index-header">
                    <th colspan="2">
                        <div class="leap-buttons">
                            @can('leap::create')
                                <button x-on:click="$wire.createDirectory({{ $depth }},prompt('@lang('New folder')'))" class="leap-button">@svg('fas-folder-plus', 'svg-icon')<span> @lang('New folder')</span></button>
                            @endcan
                            @if ($depth > 0)
                                @can('leap::delete')
                                    @if (count($directory['folders']) == 0 && count($directory['files']) == 0)
                                        <button
                                            wire:click="deleteDirectory({{ $depth }})"
                                            wire:confirm="@lang('delete_folder'). @lang('are_you_sure')"
                                            class="leap-button secondary">
                                            @svg('fas-trash-alt', 'svg-icon')<span> @lang('delete_folder')</span>
                                        </button>
                                    @endcan
                                @endif
                            @endif
                        </div>
                    </th>
                </tr>
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
                    <tr x-on:click="$wire.selectFile('{{ urlencode($name) }}',{{ $depth }},window.event.altKey||window.event.metaKey,window.event.shiftKey)" class="leap-index-row @if ($depth == count($openFolders) && in_array($name, $selectedFiles)) leap-index-row-selected @endif">
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
                    <x-leap::button svg-icon="fas-xmark" x-on:click="$wire.selectFile" label="Close" />
                </div>
                <div class="leap-filemanager-preview">
                    <div class="leap-filemanager-preview-items" style="grid-template-columns: repeat({{ ceil(sqrt(count($selectedFiles))) }}, 1fr)">
                        @foreach ($selectedFiles as $file)
                            <div class="leap-filemanager-preview-item">
                                @if ($this->isImage($file))
                                    <img src="{{ $this->downloadUrl($file) }}" alt="">
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
                            {{ count($selectedFiles) }} @lang('files')
                        @else
                            {{ reset($selectedFiles) }}
                        @endif
                    </h3>
                    <table>
                        @foreach ($this->selectedFilesStats() as $key => $value)
                            @if ($value)
                                <tr>
                                    <td>{{ __($key) }}</td>
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
                let e = document.querySelector('.leap-index');
                e.scrollTo({
                    left: e.scrollWidth,
                    behavior: 'smooth'
                });
            });
        </script>
    @endscript
</main>
