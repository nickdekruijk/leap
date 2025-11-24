@props(['attribute', 'placeholder', 'name'])

<x-leap::label>
    <textarea
        x-autosize
        class="leap-textarea"
        @error($attribute->dataName ?? $name) 
            aria-errormessage="{{ $message }}" 
            aria-invalid="true"
        @enderror
        placeholder="{{ $placeholder[$attribute->name] ?? $attribute->placeholder }}"
        @if ($attribute->disabled ?? false) disabled @endif
        wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="{{ $attribute->dataName }}"
        {{ $attribute->inputAttributes() }}
        {{ $attributes }}>
    </textarea>
</x-leap::label>
