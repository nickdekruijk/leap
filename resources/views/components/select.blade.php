@props(['attribute', 'name', 'label', 'placeholder'])

<x-leap::label>
    <select class="leap-select"
        @error($attribute->dataName ?? $name) aria-errormessage="{{ $message }}" aria-invalid="true" @enderror
        @if ($attribute->disabled ?? false) disabled @endif
        wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="{{ $attribute->dataName }}"
        {{ $attribute->inputAttributes() }}
        aria-label="@lang($attribute->label ?? ($label ?? $name))"
        {{ $attributes }}>
        @if ($attribute->placeholder)
            <option value="">{{ $attribute->placeholder }}</option>
        @endif
        @foreach ($attribute->getValues() as $key => $value)
            <option value="{{ $key }}">{{ $value }}</option>
        @endforeach
    </select>
</x-leap::label>
