@props(['attribute', 'placeholder', 'name'])

@assets
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
@endassets

<x-leap::label>
    <div x-data="{ value: $wire.entangle('{{ $attribute->dataName }}') }" wire:ignore>
        <div
            x-ref="editor_{{ $attribute->name }}"
            x-init="quill = new Quill($refs.editor_{{ $attribute->name }}, {
                theme: 'snow'
            });
            {{-- console.log(value); --}}
            quill.clipboard.dangerouslyPasteHTML(value || '');
            quill.on('text-change', function() {
                @this.set('{{ $attribute->dataName }}', quill.getContents())
            });
            $watch('value', function(newValue) {
                {{-- console.log(newValue, value, typeof newValue, typeof value); --}}
                if (typeof newValue !== 'object' && newValue !== null) {
                    console.log(newValue);
                    quill.clipboard.dangerouslyPasteHTML(newValue || '');
                }
                {{-- if (newValue !== quill.getContents()) { --}}
                {{-- quill.setContents(newValue); --}}
                {{-- } --}}
            });">
        </div>
    </div>
</x-leap::label>
