@props(['attribute', 'name', 'label', 'placeholder'])

@php
    $definitions = collect($attribute->sections)->keyBy('name');
    // In display order, with what the list needs to know about each row: is it a
    // divider, which group is it in, is that group folded away.
    $layout = $this->sectionLayout($attribute->name);
    $thumbnails = $this->sectionThumbnails($attribute->name);
    // The rows on screen: the first one can have a section put in front of it, and the
    // last one leaves adding below it to the control under the list.
    $rows = array_keys(array_filter($layout, fn ($row) => !$row['hidden']));
    $firstRow = $rows[0] ?? null;
    $lastRow = $rows ? end($rows) : null;
@endphp

<x-leap::label>
    @if ($this->hasOpenSection($attribute->name))
        <button wire:click="toggleAllSections('{{ $attribute->name }}',1)" class="leap-button-add" type="button">@svg('far-square-minus', 'svg-icon')</button>
    @endif
    @if ($this->hasClosedSection($attribute->name))
        <button wire:click="toggleAllSections('{{ $attribute->name }}',0)" class="leap-button-add" type="button">@svg('far-square-plus', 'svg-icon')</button>
    @endif
</x-leap::label>
<div class="leap-editor-sections">
    <ul class="leap-editor-sections" x-sort.ghost="$wire.sortSection('{{ $attribute->name }}', $item, $position)" x-sort:config="{ swapThreshold: .5 }">
        @foreach ($layout as $index => $row)
            {{-- A section in a folded group is not drawn at all, so the positions the
                 browser reports while sorting count the rows that are on screen. --}}
            @continue($row['hidden'])
            @php
                $sectionContent = $this->data[$attribute->name][$index];
                $section = $definitions->get($sectionContent['_name']);
                $closed = !empty($sectionContent['_closed']);
                $folded = $row['divider'] && !empty($sectionContent['_folded']);
                $summary = $closed ? $this->sectionSummary($sectionContent, $section) : '';
            @endphp
            <li x-sort:item="{{ $index }}" wire:key="{{ $attribute->name }}-{{ $index }}" x-sort:group=""
                @class([
                    'leap-editor-section',
                    'leap-editor-section-closed' => $closed,
                    'leap-editor-section-divider' => $row['divider'],
                    'leap-editor-section-grouped' => $row['group'] !== null,
                    // The same test HasSections uses to leave a section off the page
                    'leap-editor-section-inactive' => isset($sectionContent['active']) && !$sectionContent['active'],
                ])>
                <label x-sort:handle class="leap-label" wire:click="toggleSection('{{ $attribute->name }}', {{ $index }})">
                    <span class="leap-label">
                        @svg('fas-grip-vertical', 'svg-icon')
                        {{-- The kind of section is a narrow column in front of what it
                             holds; with nothing to show beside it, it takes the row. --}}
                        <span @class(['leap-section-name', 'leap-section-name-only' => $summary === '']) title="{{ $section?->label ?: $sectionContent['_name'] }}">{{ $section?->label ?: $sectionContent['_name'] }}</span>
                        @if ($summary !== '')
                            <span class="leap-label-sub">{{ $summary }}</span>
                        @endif
                        @if ($folded)
                            <span class="leap-section-count">{{ $row['members'] }}</span>
                        @endif
                    </span>
                </label>
                @if ($row['divider'])
                    {{-- Beside the label, not in it: a <label> hands a click anywhere on
                         it to the first button inside, and this one does something else. --}}
                    <button type="button" class="leap-section-fold" wire:click="toggleSectionGroup('{{ $attribute->name }}', {{ $index }})"
                        aria-expanded="{{ $folded ? 'false' : 'true' }}" aria-label="{{ __($folded ? 'leap::resource.unfold_group' : 'leap::resource.fold_group') }}">
                        @svg($folded ? 'fas-chevron-right' : 'fas-chevron-down', 'svg-icon')
                    </button>
                @endif
                {{-- A column of its own once any section has an image, so every row
                     ends at the same place. --}}
                @if ($closed && isset($thumbnails[$index]))
                    <img class="leap-section-thumbnail" loading="lazy" src="{{ $thumbnails[$index]->downloadUrl }}" alt="">
                @elseif ($closed && $thumbnails)
                    <span class="leap-section-thumbnail"></span>
                @endif
                <span class="leap-section-actions">
                    @can('leap::update')
                        <x-leap::button svg-icon="far-copy" wire:click="duplicateSection('{{ $attribute->name }}', {{ $index }})" label="leap::resource.duplicate" wire:loading.delay.shorter.attr="disabled" type="button" />
                    @endcan
                    @can('leap::delete')
                        <x-leap::button svg-icon="far-trash-alt" wire:click="removeSection('{{ $attribute->name }}', {{ $index }})" wire:confirm="{{ __('leap::resource.delete_confirm') }}" label="leap::resource.delete" wire:loading.delay.shorter.attr="disabled" class="secondary" />
                    @endcan
                </span>
                {{-- Add a section below this one: a select dressed as a button, so one
                     click opens the list of sections. Below rather than above, so the
                     one under the last section of a group is plainly inside that group.
                     Inside the row, because every child of the list is a row to the
                     sorting. The last row has none: the control under the list is it. --}}
                @if ($index !== $lastRow)
                    <select class="leap-section-insert" aria-label="@lang('leap::resource.insert_section')" title="@lang('leap::resource.insert_section')"
                        x-on:change="$wire.insertSection('{{ $attribute->name }}', $event.target.value, {{ $index }}, true); $event.target.value = ''; $event.target.blur()">
                        <option value="">@lang('leap::resource.insert_section')</option>
                        @foreach ($attribute->sections as $option)
                            <option value="{{ $option->name }}">{{ $option->label }}</option>
                        @endforeach
                    </select>
                    {{-- Where the section will come, drawn while the "+" is pointed at.
                         An element of its own: a select cannot carry a ::after. --}}
                    <span class="leap-section-insert-line"></span>
                @endif
                {{-- And the one place "below" cannot reach: in front of the first row --}}
                @if ($index === $firstRow)
                    <select class="leap-section-insert leap-section-insert-before" aria-label="@lang('leap::resource.insert_section')" title="@lang('leap::resource.insert_section')"
                        x-on:change="$wire.insertSection('{{ $attribute->name }}', $event.target.value, {{ $index }}); $event.target.value = ''; $event.target.blur()">
                        <option value="">@lang('leap::resource.insert_section')</option>
                        @foreach ($attribute->sections as $option)
                            <option value="{{ $option->name }}">{{ $option->label }}</option>
                        @endforeach
                    </select>
                    <span class="leap-section-insert-line leap-section-insert-before"></span>
                @endif
                @if (!$closed)
                    <fieldset class="leap-fieldset">
                        @if ($section)
                            @foreach ($section->attributes as $sectionAttribute)
                                <x-dynamic-component :component="'leap::' . $sectionAttribute->input" :attribute="$this->sectionAttribute($sectionAttribute, $attribute->name, $index, $sectionContent['_name'], $section)" :placeholder="$placeholder" :value="$this->sectionValue($sectionContent, $sectionAttribute)" />
                            @endforeach
                        @else
                            {{-- No section definition matches _name: the section was renamed or removed
                                 and this record still has it. Dump what is stored so it is not lost
                                 silently; a value can be an array here, which e() cannot render. --}}
                            @foreach ($sectionContent as $key => $value)
                                @if ($key != '_name' && $key != '_sort')
                                    <div>{{ $key }}: {{ is_scalar($value) || $value === null ? $value : json_encode($value) }}</div>
                                @endif
                            @endforeach
                        @endif
                    </fieldset>
                @endif
            </li>
        @endforeach
    </ul>

    {{-- Add a section at the end. The same "+" as between the rows, but always there and
         with its name beside it: it is what an empty list starts from, and what somebody
         who has not found the ones on hover, or works on a tablet, adds a section with.
         Stepped in when the end of the list is inside a group. --}}
    <select @class(['leap-section-add', 'leap-section-add-grouped' => $lastRow !== null && ($layout[$lastRow]['divider'] || $layout[$lastRow]['group'] !== null)])
        aria-label="{{ strip_tags(__((string) ($attribute->label ?? ($label ?? $name)))) }}: @lang('leap::resource.add_section')"
        x-on:change="$wire.insertSection('{{ $attribute->name }}', $event.target.value); $event.target.value = ''">
        <option value="">@lang('leap::resource.add_section')</option>
        @foreach ($attribute->sections as $section)
            <option value="{{ $section->name }}">{{ $section->label }}</option>
        @endforeach
    </select>

</div>
