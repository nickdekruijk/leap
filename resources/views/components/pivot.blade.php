@props(['attribute', 'name', 'label', 'placeholder'])

<x-leap::label>
</x-leap::label>

<div class="leap-pivot">
    @foreach ($attribute->getValues() as $key => $value)
        <label><input type="checkbox" wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="data.{{ $attribute->name }}" value="{{ $key }}" aria-label="{{ $value }}">{{ $value }}</label>
    @endforeach
</div>
