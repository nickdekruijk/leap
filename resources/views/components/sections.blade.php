@props(['attribute', 'name', 'label', 'placeholder'])

<x-leap::label></x-leap::label>

<div class="leap-editor-sections">
    <ul class="leap-editor-sections" x-sort.ghost="$wire.sortSection('{{ $attribute->name }}', $item, $position)" x-sort:config="{ swapThreshold: .5 }">
        @foreach (collect($this->data[$attribute->name])->sortBy('_sort') ?: [] as $index => $sectionContent)
            <li x-sort:item="{{ $index }}" class="leap-editor-section" wire:key="{{ $attribute->name }}-{{ $index }}" x-sort:group="">
                <label x-sort:handle class="leap-label">
                    <span class="leap-label">{{ ($section = collect($attribute->sections)->where('name', $sectionContent['_name'])->first())?->label ?: $sectionContent['_name'] }}</span>
                    @svg('fas-arrows-alt-v', 'svg-icon')
                </label>
                @can('leap::delete')
                    <x-leap::button svg-icon="far-trash-alt" wire:click="removeSection('{{ $attribute->name }}', {{ $index }})" wire:confirm="{{ __('leap::resource.delete_confirm') }}" label="leap::resource.delete" wire:loading.delay.shorter.attr="disabled" class="secondary" />
                @endcan
                <fieldset class="leap-fieldset">
                    @if ($section)
                        @foreach ($section->attributes as $sectionAttribute)
                            <x-dynamic-component :component="'leap::' . $sectionAttribute->input" :attribute="$this->sectionAttribute($sectionAttribute, $attribute->name, $index, $sectionContent['_name'])" :placeholder="$placeholder" />
                        @endforeach
                    @else
                        @foreach ($sectionContent as $key => $value)
                            @if ($key != '_name' && $key != '_sort')
                                <div>{{ $key }}: {{ $value }}</div>
                            @endif
                        @endforeach
                    @endif
                </fieldset>
            </li>
        @endforeach
    </ul>

    <select class="leap-select leap-select-button"
        wire:model.live="{{ $attribute->dataName }}:add"
        aria-label="@lang($attribute->label ?? ($label ?? $name))">
        <option value="">@lang('leap::resource.add_section')</option>
        @foreach ($attribute->sections as $section)
            <option value="{{ $section->name }}">{{ $section->label }}</option>
        @endforeach
    </select>

</div>
