@props(['attribute', 'name', 'label', 'placeholder'])

@php
    $definitions = collect($attribute->sections)->keyBy('name');
    // In display order, with what the list needs to know about each row: is it a
    // divider, which group is it in, is that group folded away.
    $layout = $this->sectionLayout($attribute->name);
    $thumbnails = $this->sectionThumbnails($attribute->name);
    // The rows on screen: whether the last one is in a group says where the control
    // under the list sits.
    $rows = array_keys(array_filter($layout, fn ($row) => !$row['hidden']));
    $firstRow = $rows[0] ?? null;
    $lastRow = $rows ? end($rows) : null;
    // Edited in a modal: the list stays a list, and a click opens a section's fields there
    $modal = $this->sectionsInModal($attribute->name);
@endphp

{{-- tag="div": a <label> hands a click anywhere on it to the first button inside --}}
<x-leap::label tag="div">
    @if (!$modal && $this->hasOpenSection($attribute->name))
        <button wire:click="toggleAllSections('{{ $attribute->name }}',1)" class="leap-button-add" type="button">@svg('far-square-minus', 'svg-icon')</button>
    @endif
    @if (!$modal && $this->hasClosedSection($attribute->name))
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
                // The section open in the modal is closed here meanwhile: its fields
                // cannot be on the page twice, two editors on one value would fight.
                $editing = ($this->sectionModal['attribute'] ?? null) === $attribute->name && ($this->sectionModal['index'] ?? null) === $index;
                $closed = $modal || $editing || !empty($sectionContent['_closed']);
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
                <label x-sort:handle class="leap-label" @if ($modal) wire:click="editSection('{{ $attribute->name }}', {{ $index }})" @else wire:click="toggleSection('{{ $attribute->name }}', {{ $index }})" @endif>
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
                    {{-- In modal mode a click on the row opens the modal; otherwise this does,
                         for a closed section. An open one is being edited right here. --}}
                    @if (!$modal && !empty($sectionContent['_closed']))
                        <x-leap::button svg-icon="fas-pen" wire:click="editSection('{{ $attribute->name }}', {{ $index }})" label="leap::resource.edit" type="button" class="leap-section-edit" />
                    @endif
                    {{-- Insert puts a section above this row: on the first row that is the
                         top of the list, and on a divider it is the end of the group above
                         it. The end of the list has its own control under it. The button is
                         what shows, and the select lies over it unseen and opens on the
                         same click: a select on its own is as wide as its longest option,
                         which with long section names is half the row. --}}
                    <span class="leap-button leap-section-insert">
                        @svg('fas-plus', 'leap-svg-icon')@lang('leap::resource.insert')
                        <select aria-label="@lang('leap::resource.insert_section')" title="@lang('leap::resource.insert_section')" x-on:change="$wire.insertSection('{{ $attribute->name }}', $event.target.value, {{ $index }}); $event.target.value = ''; $event.target.blur()">
                            <option value="">@lang('leap::resource.insert')</option>
                            @foreach ($attribute->sections as $option)
                                <option value="{{ $option->name }}">{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </span>
                    @can('leap::update')
                        <x-leap::button svg-icon="far-copy" wire:click="duplicateSection('{{ $attribute->name }}', {{ $index }})" label="leap::resource.duplicate" wire:loading.delay.shorter.attr="disabled" type="button" class="leap-section-duplicate" />
                    @endcan
                    @can('leap::delete')
                        <x-leap::button svg-icon="far-trash-alt" wire:click="removeSection('{{ $attribute->name }}', {{ $index }})" wire:confirm="{{ __('leap::resource.delete_confirm') }}" label="leap::resource.delete" wire:loading.delay.shorter.attr="disabled" class="secondary" />
                    @endcan
                </span>
                {{-- Where the section will come, drawn while the button is pointed at:
                     above the row for Insert, below it for Duplicate. Elements of their
                     own: a select cannot carry a ::after. --}}
                <span class="leap-section-insert-line leap-section-insert-line-top"></span>
                <span class="leap-section-insert-line"></span>
                @if (!$closed)
                    <x-leap::section-fields :attribute="$attribute" :index="$index" :section-content="$sectionContent" :section="$section" :placeholder="$placeholder" />
                @endif
            </li>
        @endforeach
    </ul>

    {{-- Add a section at the end. The same "+" as between the rows, but always there and
         with its name beside it: it is what an empty list starts from, and what somebody
         who has not found the ones on hover, or works on a tablet, adds a section with.
         Stepped in when the end of the list is inside a group. --}}
    <span @class(['leap-section-add', 'leap-section-add-grouped' => $lastRow !== null && ($layout[$lastRow]['divider'] || $layout[$lastRow]['group'] !== null)])>
        @svg('fas-plus', 'leap-svg-icon')@lang('leap::resource.add_section')
        <select aria-label="{{ strip_tags(__((string) ($attribute->label ?? ($label ?? $name)))) }}: @lang('leap::resource.add_section')"
            x-on:change="$wire.insertSection('{{ $attribute->name }}', $event.target.value); $event.target.value = ''; $event.target.blur()">
            <option value="">@lang('leap::resource.add_section')</option>
            @foreach ($attribute->sections as $option)
                <option value="{{ $option->name }}">{{ $option->label }}</option>
            @endforeach
        </select>
    </span>

    @if (($this->sectionModal['attribute'] ?? null) === $attribute->name && isset($this->data[$attribute->name][$this->sectionModal['index']]))
        @php
            $index = $this->sectionModal['index'];
            $sectionContent = $this->data[$attribute->name][$index];
            $section = $definitions->get($sectionContent['_name']);
        @endphp
        {{-- Teleported to the root of the panel: the editor slides in with a transform,
             which would make a fixed overlay cover only the editor and scroll along.
             Backdrop and Escape mean Done, so nothing typed is ever lost by a stray
             click; but not while the file browser or a TinyMCE dialog is open on top. --}}
        <div x-data wire:key="{{ $attribute->name }}-modal-{{ $index }}">
            {{-- Shown once the rich text in it has started: TinyMCE grows to its content,
                 and a centred dialog grows both ways, so its header and fields would jump
                 up while you look at them. The backdrop comes straight away, the dialog
                 when every eager editor has said so, or after a second if one does not. --}}
            <x-leap::modal show="true" teleport class="leap-section-modal"
                x-data="{ waiting: 0, ready: false }"
                x-init="waiting = $el.querySelectorAll('[data-leap-richtext=eager]').length; if (!waiting) ready = true; setTimeout(() => ready = true, 1000)"
                x-on:leap-richtext-ready="if (--waiting <= 0) ready = true"
                x-bind:class="{ 'leap-section-modal-ready': ready }"
                close="if (!document.querySelector('.leap-filebrowser, .tox-dialog')) $wire.closeSection()">
                {{-- Title and buttons stay at the top, and only the fields below them
                     scroll. That way TinyMCE's sticky toolbar, which sticks to the top of
                     whatever scrolls, stays under the bar instead of behind it. --}}
                <header class="leap-header leap-section-modal-bar">
                    <h2>{{ $section?->label ?: $sectionContent['_name'] }}</h2>
                    <x-leap::button svg-icon="far-check-circle" wire:click="closeSection" label="leap::resource.done" class="primary" type="button" />
                    @can('leap::update')
                        <x-leap::button svg-icon="far-floppy-disk" wire:click="saveSection" label="leap::resource.done_save" wire:loading.delay.shorter.attr="disabled" type="button" />
                    @endcan
                    <x-leap::button svg-icon="fas-times" wire:click="cancelSection" label="leap::resource.cancel" type="button" />
                </header>
                <div class="leap-section-modal-body">
                    <x-leap::section-fields :attribute="$attribute" :index="$index" :section-content="$sectionContent" :section="$section" :placeholder="$placeholder" />
                </div>
            </x-leap::modal>
        </div>
    @endif

</div>
