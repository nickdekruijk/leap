<main class="leap-main" x-data="{ selectedRow: $wire.entangle('selectedRow') }" x-init="if (selectedRow) $dispatch('openEditor', { id: selectedRow })" x-bind:class="selectedRow ? 'leap-editor-open' : ''">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
        @can('leap::create')
            <x-leap::button svg-icon="fas-circle-plus" x-on:click="$dispatch('openEditor',{id:(selectedRow=-1)})" label="leap::resource.create_new" class="primary" />
        @endcan
        @can('leap::read')
            @isset($this->downloadCSV)
                <x-leap::button svg-icon="fas-download" wire:click="downloadCSVfile()" label="leap::resource.downloadCSV" />
            @endisset
        @endcan
    </header>
    <div class="leap-index" @if ($this->treeview()) x-data="{ sortGroup: false }" x-init="window.setColumnWidths($el);$watch('$wire.setColumnWidths', () => $nextTick(() => setColumnWidths($el)))" @endif>
        @include('leap::livewire.resource-index', ['parent_id' => null, 'depth' => 0])
    </div>
    @livewire('leap.editor')
    @if ($browse)
        <div class="leap-filebrowser" x-on:keydown.escape.window="$wire.fileBrowser" x-data="{ open: true }">
            <div class="leap-filebrowser-dialog" x-on:click.outside="$wire.fileBrowser" x-trap.inert="open">
                @livewire('leap.filemanager', ['browse' => $browse])
            </div>
        </div>
    @endif
</main>

@if ($this->treeview())
    @script
        <script>
            window.setColumnWidths = function(el) {
                let widths = [];
                // Get maximum column widths
                let spacing = parseInt(getComputedStyle(document.querySelector(':root')).getPropertyValue('--spacing').replace('px', ''));
                let columnCount = el.querySelectorAll('.leap-index-header .leap-index-column').length;
                document.querySelectorAll('.leap-index-header, .leap-index-row').forEach(function(row) {
                    let depth = parseInt(row.getAttribute('data-depth'));
                    row.querySelectorAll('.leap-index-column').forEach(function(column, index) {
                        if (widths[index] < column.offsetWidth || !widths[index]) widths[index] = column.offsetWidth + depth * spacing;
                    });
                });
                // Apply column widths to all rows
                document.querySelectorAll('.leap-index-header, .leap-index-row').forEach(function(row) {
                    let depth = parseInt(row.getAttribute('data-depth'));
                    row.querySelectorAll('.leap-index-column').forEach(function(column, index) {
                        if (index < columnCount - 1) { // Don't set last column
                            if (index == 0 && depth) {
                                // Add extra spacing to first column
                                column.style.width = widths[index] + spacing - depth * spacing + 'px';
                            } else {
                                column.style.width = widths[index] + spacing + 'px';
                            }
                        }
                    });
                });
            }
        </script>
    @endscript
@endif
