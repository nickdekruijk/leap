@props(['attribute', 'placeholder', 'name'])

<x-leap::label>
    <input class="leap-input"
        @error($attribute->dataName ?? $name) aria-errormessage="{{ $message }}" aria-invalid="true" @enderror
        @isset($attribute)
            placeholder="{{ $placeholder[$attribute->name] ?? $attribute->placeholder }}"
            @if (auth(config('leap.guard'))->user() && Gate::denies('leap::create') && Gate::denies('leap::update')) disabled @endif
            wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="{{ $attribute->dataName }}"
            {{ $attribute->inputAttributes() }}
        @endisset
        {{ $attributes }}>
</x-leap::label>
