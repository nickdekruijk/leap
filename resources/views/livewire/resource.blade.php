<main class="leap-main">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <div class="leap-index-with-editor" x-data="{ active: false }">
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
                    <tr x-on:click="active={{ $row['id'] }}" wire:click="$dispatch('openEditor',{id:{{ $row['id'] }}})" x-bind:class="active == {{ $row['id'] }} ? 'leap-index-row-selected' : ''" class="leap-index-row">
                        @foreach ($this->indexAttributes() as $attribute)
                            <td>{{ $row[$attribute->name] }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
        @livewire('leap.editor')
    </div>
</main>
