@props(['attribute', 'name', 'label', 'placeholder'])

<x-leap::label></x-leap::label>

<fieldset class="leap-fieldset" @if ($attribute->options['group']) role="group" @endif>
    @foreach ($attribute->values as $key => $value)
        <label class="leap-label">
            <span class="leap-label">{{ $value }}</span>
            <input class="leap-input" type="radio"
                @error($attribute->dataName ?? $name) aria-errormessage="{{ $message }}" aria-invalid="true" @enderror
                @if ($attribute->disabled ?? false) disabled @endif
                wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="{{ $attribute->dataName }}"
                value="{{ $key }}"
                {{ $attribute->inputAttributes() }}
                aria-label="{{ $value }}"
                {{ $attributes }}>
        </label>
    @endforeach
</fieldset>
