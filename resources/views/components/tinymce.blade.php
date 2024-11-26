@props(['attribute', 'placeholder', 'name'])

@assets
    <script src="{{ config('leap.tinymce.cdn') }}" defer></script>
@endassets

<x-leap::label>
    <div
        wire:ignore
        x-data="{ value: $wire.entangle('{{ $attribute->dataName }}') }"
        x-init="tinymce.init(Object.assign({{ json_encode(config('leap.tinymce.options')) }}, {
            target: $refs.textarea,
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
            @if (auth(config('leap.guard'))->user() && Gate::denies('leap::create') && Gate::denies('leap::update')) disabled @endif
            {{ $attribute->inputAttributes() }}
            {{ $attributes }}>
        </textarea>
    </div>
</x-leap::label>
