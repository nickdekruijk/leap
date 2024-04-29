<main class="leap-main">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <div class="leap-resource">
        <div class="leap-index">
            <table class="leap-index-table">
                <tr class="leap-index-header">
                    @foreach ($this->indexAttributes() as $attribute)
                        <th wire:click="order('{{ $attribute->name }}')" class="{{ $this->orderBy === $attribute->name ? ($this->orderDesc ? 'order-desc' : 'order-asc') : '' }}">{{ $attribute->label_index }}</th>
                    @endforeach
                </tr>
                @foreach ($this->indexRows as $row)
                    @if ($this->orderBy && $this->getAttribute($this->orderBy)->type != 'number' && ($char = ucfirst(substr($row[$this->orderBy], 0, 1))) && (empty($last) || $last !== $char))
                        <tr class="leap-index-row leap-index-group">
                            @foreach ($row as $attribute => $value)
                                <td>{{ $attribute == $this->orderBy ? ($last = $char) : '' }}</td>
                            @endforeach
                            {{-- <td colspan="{{ count($this->indexAttributes()) }}">{{ $last = $char }}</td> --}}
                        </tr>
                    @endif
                    <tr wire:click="open({{ $row['id'] }})" class="leap-index-row">
                        @foreach ($row as $attribute => $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</main>
