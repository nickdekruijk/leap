<main class="leap-main" x-data="{ selectedRow: $wire.entangle('selectedRow') }" x-init="if (selectedRow) $dispatch('openEditor', { id: selectedRow })" x-bind:class="selectedRow ? 'leap-editor-open' : ''">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
        @can('leap::create')
            <x-leap::button svg-icon="fas-circle-plus" x-on:click="$dispatch('openEditor',{id:(selectedRow=-1)})" label="leap::resource.create_new" class="primary" />
        @endcan
    </header>
    <div class="leap-index">
        <table class="leap-index-table">
            <tr class="leap-index-header">
                @foreach ($this->indexAttributes() as $attribute)
                    <th class="{{ $this->orderBy === $attribute->name ? ($this->orderDesc ? 'order-desc' : 'order-asc') : '' }}"><button class="button-link" wire:click="order('{{ $attribute->name }}')">{{ $attribute->labelIndex }}</button></th>
                @endforeach
            </tr>
            @foreach ($this->indexRows() as $row)
                @if ($this->orderBy && $this->getAttribute($this->orderBy)->type != 'number' && strlen($char = ucfirst(mb_substr($row[$this->orderBy], 0, 1))) && (empty($last) || $last !== $char))
                    <tr class="leap-index-row leap-index-group">
                        @foreach ($this->indexAttributes() as $attribute)
                            <td>{{ $attribute->name == $this->orderBy ? ($last = $char) : '' }}</td>
                        @endforeach
                    </tr>
                @endif
                <tr x-on:click="$dispatch('openEditor',{id:(selectedRow={{ $row['id'] }})})" x-bind:class="selectedRow == {{ $row['id'] }} ? 'leap-index-row-selected' : ''" class="leap-index-row">
                    @foreach ($this->indexAttributes() as $attribute)
                        <td>
                            @if ($loop->first)
                                <button class="button-link">
                            @endif
                            @if ($attribute->type == 'checkbox')
                                <span class="leap-row-checkbox leap-row-checkbox-{{ $row[$attribute->name] ? 'checked' : 'unchecked' }}"></span>
                            @else
                                {{ $row[$attribute->name] }}
                            @endif
                            @if ($loop->first)
                                </button>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
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
