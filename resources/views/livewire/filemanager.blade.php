<main class="leap-main leap-filemanager">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <div class="leap-index">
        @foreach ($directories as $depth => $directory)
            <table class="leap-index-table">
                @if ($depth > 0)
                    <tr wire:click="closeDirectory({{ $depth - 1 }})" class="leap-index-header leap-filemanager-parent">
                        <th colspan="2">
                            <button class="button-link">@svg('fas-arrow-left', 'svg-icon') {{ urldecode(end($openFolders)) }}</button>
                        </th>
                    </tr>
                @endif
                @foreach ($directory['folders'] as $folder)
                    <tr wire:click="openDirectory('{{ $folder->encoded }}',{{ $depth + 1 }})" class="leap-index-row @if (in_array($folder->encoded, $openFolders)) leap-index-row-selected @endif">
                        <td><button class="button-link">@svg('fas-folder' . (in_array($folder->encoded, $openFolders) ? '-open' : ''), 'svg-icon') {{ $folder->name }}</button></td>
                        <td align="right">{{ $folder->size }}</td>
                    </tr>
                @endforeach
                @foreach ($directory['files'] as $file)
                    <tr x-on:click="$wire.selectFile('{{ $file->encoded }}',window.event.altKey||window.event.metaKey,window.event.shiftKey)" class="leap-index-row @if (in_array($file->encoded, $selectedFiles)) leap-index-row-selected @endif">
                        <td>
                            <button class="button-link">
                                @isset($file->thumbnail)
                                    <img loading="lazy" draggable="false" class="thumbnail" src="{{ $file->thumbnail }}" alt="">
                                @endisset
                                @svg($this->fileIcon($file), 'svg-icon')
                                {{ $file->name }}
                            </button>
                        </td>
                        <td align="right">{{ $file->size }}</td>
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
                        @foreach (collect($selectedFiles)->sort(SORT_NATURAL | SORT_FLAG_CASE) as $file)
                            <div class="leap-filemanager-preview-item">
                                {!! $this->getPreview($file) !!}
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="leap-filemanager-stats">
                    <h3>
                        @if (count($selectedFiles) > 1)
                            {{ count($selectedFiles) }} @lang('files')
                        @else
                            {{ basename(rawurldecode(reset($selectedFiles))) }}
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
