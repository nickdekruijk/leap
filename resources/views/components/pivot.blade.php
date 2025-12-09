@props(['attribute', 'name', 'label', 'placeholder'])

<x-leap::label>
</x-leap::label>

<div class="leap-pivot"
    @error($attribute->dataName ?? ($name ?? '')) aria-errormessage="{{ $message }}" aria-invalid="true" @enderror>
    @foreach ($attribute->getValues() as $key => $value)
        <label><input type="checkbox" wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="data.{{ $attribute->name }}" value="{{ $key }}" aria-label="{{ $value }}">{{ $value }}</label>
    @endforeach
</div>
