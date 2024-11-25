@props(['attribute', 'placeholder', 'name'])

@assets
    <script src="{{ config('leap.tinymce.cdn') }}" defer></script>
@endassets

<x-leap::label>
    <div
        wire:ignore
        x-data="{ value: $wire.entangle('{{ $attribute->dataName }}') }"
        x-init="tinymce.init({
            target: $refs.textarea,
            theme: 'silver',
            height: 200,
            license_key: 'gpl',
            menubar: false,
            promotion: false,
            branding: false,
            plugins: 'autoresize code fullscreen',
            autoresize_bottom_margin: 50,
            max_height: window.innerHeight / 1.5,
            toolbar: 'code fullscreen undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            {{-- toolbar_sticky: true, --}}
            {{-- toolbar_sticky_offset: -200, --}}
            setup: function(editor) {
                editor.on('blur', function(e) {
                    value = editor.getContent()
                });
                editor.on('init', function(e) {
                    if (value != null) {
                        editor.setContent(value)
                    }
                });
        
                function putCursorToEnd() {
                    editor.selection.select(editor.getBody(), true);
                    editor.selection.collapse(false);
                };
                $watch('value', function(newValue) {
                    if (newValue !== editor.getContent()) {
                        editor.resetContent(newValue || '');
                        putCursorToEnd();
                    }
                });
            }
        })">
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
