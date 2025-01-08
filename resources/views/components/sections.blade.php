@props(['attribute', 'name', 'label', 'placeholder'])

<x-leap::label></x-leap::label>

<div class="leap-editor-sections">
    <select class="leap-select"
        wire:model.live="{{ $attribute->dataName }}:add"
        aria-label="@lang($attribute->label ?? ($label ?? $name))">
        <option value="">@lang('leap::resource.add_section')</option>
        @foreach ($attribute->sections as $section)
            <option value="{{ $section->name }}">{{ $section->label }}</option>
        @endforeach
    </select>

    <ul class="leap-editor-sections" x-sort.ghost="$wire.sortSection('{{ $attribute->name }}', $item, $position); $nextTick(() => console.log($el))" x-sort:config="{ swapThreshold: .5 }">
        @foreach ($this->data[$attribute->name] ?: [] as $index => $sectionContent)
            <li x-sort:item="{{ $index }}" class="leap-editor-section" wire:key="{{ $attribute->name }}-{{ $index }}">
                <label x-sort:handle class="leap-label">
                    <span class="leap-label">{{ ($section = collect($attribute->sections)->where('name', $sectionContent['_name'])->first())?->label }}</span>
                    @svg('fas-arrows-alt-v', 'svg-icon')
                </label>
                @can('leap::delete')
                    <x-leap::button svg-icon="far-trash-alt" wire:click="removeSection('{{ $attribute->name }}', {{ $index }})" wire:confirm="{{ __('leap::resource.delete_confirm') }}" label="leap::resource.delete" wire:loading.delay.shorter.attr="disabled" class="secondary" />
                @endcan
                <fieldset class="leap-fieldset">
                    @foreach ($section?->attributes ?: [] as $sectionAttribute)
                        @php($sectionAttribute->dataName = $attribute->dataName . '.' . $index . '.' . $sectionAttribute->name)
                        <x-dynamic-component :component="'leap::' . $sectionAttribute->input" :attribute="$sectionAttribute" :placeholder="$placeholder" />
                        {{-- {{ $this->data[$attribute->name][$index][$sectionAttribute->name] }} --}}
                    @endforeach
                </fieldset>
            </li>
        @endforeach
    </ul>

</div>
