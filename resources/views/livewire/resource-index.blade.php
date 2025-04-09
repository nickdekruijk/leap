<ul
    class="leap-index-table @if ($this->treeview()) leap-index-treeview @endif"
    @if ($this->sortable() && $this->treeview()) x-sort:config="{ 
        group: {name:'treeview', pull: function(a,b,c,d,e) { return sortGroup } }, 
        fallbackOnBody: true, 
        swapThreshold: .1}" 
        x-sort:group="treeview"
        x-sort.ghost="$wire.sortableDone({{ $parent ?? 0 }}, $item, $position)" @endif
    @if ($this->sortable() && !$this->treeview() && $this->orderBy === 'sort') x-sort:config="{ 
            fallbackOnBody: true, 
            swapThreshold: .1}" 
            x-sort.ghost="$wire.sortableDone(0, $item, $position)" @endif>
    @if ($depth == 0)
        <div class="leap-index-header">
            @foreach ($this->indexAttributes() as $attribute)
                <span class="leap-index-column {{ $this->orderBy === $attribute->name ? ($this->orderDesc ? 'order-desc' : 'order-asc') : '' }}">
                    <button class="button-link" wire:click="order('{{ $attribute->name }}')">{{ $attribute->labelIndex }}</button>
                    @if ($attribute->filterable)
                        <select class="leap-index-filter" wire:change="filterBy('{{ $attribute->name }}', $event.target.value)">
                            <option value="">&bullet;&bullet;&bullet;</option>
                            @foreach ($this->filterData($attribute) as $value)
                                <option @selected(($filters[$attribute->name] ?? null) == $value)>{{ $value }}</option>
                            @endforeach
                        </select>
                    @endif
                </span>
            @endforeach
        </div>
    @endif
    @foreach ($indexRows ?? $this->indexRows() as $row)
        @if ($this->showIndexGroups && !$this->treeview() && $this->orderBy && $this->getAttribute($this->orderBy)->type != 'number' && ($char = $this->indexGroupChar($row, $attribute)) && (empty($last) || $last !== $char))
            <div class="leap-index-row leap-index-group">
                @foreach ($this->indexAttributes() as $attribute)
                    <span class="leap-index-column">{{ $attribute->name == $this->orderBy ? ($last = $char) : '' }}</span>
                @endforeach
            </div>
        @endif
        <li @if ($this->sortable()) x-on:mouseover="sortGroup = false" x-sort:item="{{ $row['id'] }}" @endif wire:key="row-{{ $row['id'] }}">
            <div x-on:click="$dispatch('openEditor',{id:(selectedRow={{ $row['id'] }})})" x-bind:class="selectedRow == {{ $row['id'] }} ? 'leap-index-row-selected' : ''" class="leap-index-row{{ $this->active && !$row[$this->active] ? ' leap-index-row-inactive' : '' }}" data-depth="{{ $depth }}">
                @foreach ($this->indexAttributes() as $attribute)
                    <span class="leap-index-column">
                        @if ($loop->first)
                            @if ($this->treeview())
                                <span class="leap-index-sort-handle" :class="sortGroup ? 'leap-index-sort-handle-group' : ''" x-on:mouseover.stop="sortGroup = true">@svg('fas-arrows-alt', 'svg-icon')</span>
                            @endif
                            <button class="button-link">
                        @endif
                        @if ($attribute->type == 'checkbox')
                            <span class="leap-row-checkbox leap-row-checkbox-{{ $row[$attribute->name] ? 'checked' : 'unchecked' }}"></span>
                        @elseif ($attribute->input == 'select')
                            {{ $attribute->values[$row->{$attribute->name}] ?? $row->{$attribute->name} }}
                        @else
                            {{ $row->{$attribute->name} }}
                        @endif
                        @if ($loop->first)
                            </button>
                        @endif
                    </span>
                @endforeach
            </div>
            @if ($this->treeview())
                @include('leap::livewire.resource-index', ['indexRows' => $this->indexRows($row['id']), 'parent' => $row['id'], 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>
