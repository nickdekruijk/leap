@props(['attribute', 'placeholder', 'name'])

@assets
    <script src="{{ config('leap.tinymce.cdn') }}" defer></script>
@endassets

<x-leap::label>
    <div
        wire:key="{{ $attribute->dataName . $this->randomSortSeed }}"
        wire:ignore
        x-data="{ value: $wire.entangle('{{ $attribute->dataName }}') }"
        x-init="tinymce.init(Object.assign({{ json_encode($attribute->options) }}, {
            target: $refs.textarea,
            file_picker_callback: (callback, value, meta) => {
                $wire.$parent.tinymceBrowser();
                Livewire.on('tinymceBrowser', (e) => {
                    callback(e[0]);
                });
            },
            setup: function(editor) {
                editor.on('blur', function(e) {
                    value = editor.getContent()
                });
                editor.on('init', function(e) {
                    if (value != null) {
                        editor.setContent(value)
                    }
                });
                $watch('value', function(newValue) {
                    if (newValue !== editor.getContent()) {
                        editor.resetContent(newValue || '');
                        // Put cursor at the end
                        editor.selection.select(editor.getBody(), true);
                        editor.selection.collapse(false);
                    }
                });
            }
        }))">
        <textarea
            x-ref="textarea"
            class="leap-textarea"
            @error($attribute->dataName ?? $name) 
                aria-errormessage="{{ $message }}" 
                aria-invalid="true" 
            @enderror
            placeholder="{{ $placeholder[$attribute->name] ?? $attribute->placeholder }}"
            @if ($attribute->disabled ?? false) disabled @endif
            {{ $attribute->inputAttributes() }}
            {{ $attributes }}></textarea>
    </div>
</x-leap::label>
