@props(['attribute', 'index', 'sectionContent', 'section', 'placeholder'])
{{-- The fields of one section, in the list or in the modal. --}}
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
