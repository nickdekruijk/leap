<main class="leap-main" x-data="{ selectedRow: $wire.entangle('selectedRow') }" x-init="if (selectedRow) $dispatch('openEditor', { id: selectedRow })" x-bind:class="selectedRow ? 'leap-editor-open' : ''">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
        <x-leap::button svg-icon="fas-circle-plus" x-on:click="$dispatch('openEditor',{id:(selectedRow=-1)})" label="create_new" class="primary" />
    </header>
    <div class="leap-index">
        <table class="leap-index-table">
            <tr class="leap-index-header">
                @foreach ($this->indexAttributes() as $attribute)
                    <th wire:click="order('{{ $attribute->name }}')" class="{{ $this->orderBy === $attribute->name ? ($this->orderDesc ? 'order-desc' : 'order-asc') : '' }}">{{ $attribute->labelIndex }}</th>
                @endforeach
            </tr>
            @foreach ($this->indexRows() as $row)
                @if ($this->orderBy && $this->getAttribute($this->orderBy)->type != 'number' && strlen($char = ucfirst(substr($row[$this->orderBy], 0, 1))) && (empty($last) || $last !== $char))
                    <tr class="leap-index-row leap-index-group">
                        @foreach ($this->indexAttributes() as $attribute)
                            <td>{{ $attribute->name == $this->orderBy ? ($last = $char) : '' }}</td>
                        @endforeach
                    </tr>
                @endif
                <tr x-on:click="$dispatch('openEditor',{id:(selectedRow={{ $row['id'] }})})" x-bind:class="selectedRow == {{ $row['id'] }} ? 'leap-index-row-selected' : ''" class="leap-index-row">
                    @foreach ($this->indexAttributes() as $attribute)
                        <td>
                            @if ($attribute->type == 'checkbox')
                                <span class="leap-row-checkbox leap-row-checkbox-{{ $row[$attribute->name] ? 'checked' : 'unchecked' }}"></span>
                            @else
                                {{ $row[$attribute->name] }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>
    @livewire('leap.editor')
</main>
