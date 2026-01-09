@props(['attribute', 'placeholder', 'name'])

@assets
    <script src="{{ config('leap.ace.cdn') }}" defer></script>
@endassets

<x-leap::label>
    <div
        wire:key="{{ $attribute->dataName . $this->randomSortSeed }}"
        wire:ignore
        x-data="{ value: $wire.entangle('{{ $attribute->dataName }}') }"
        x-init="editor = ace.edit($refs.editor, Object.assign({{ json_encode($attribute->options) }}, {
            value: value || '',
        }));
        $watch('value', function(newValue) {
            if (newValue !== editor.getValue()) {
                editor.setValue(newValue);
            }
        });
        editor.on('blur', function(e) {
            value = editor.getValue()
        });">
        <div x-ref="editor"></div>
    </div>
</x-leap::label>
