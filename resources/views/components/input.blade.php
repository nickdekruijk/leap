@props(['attribute', 'placeholder'])

<x-leap::label>
    <input class="leap-input"
        @isset($attribute)
            @error($attribute->dataName) aria-errormessage="{{ $message }}" aria-invalid="true" @enderror
            placeholder="{{ $placeholder[$attribute->name] ?? $attribute->placeholder }}"
            @if (auth(config('leap.guard'))->user() && Gate::denies('leap::create') && Gate::denies('leap::update')) disabled @endif
            aria-label="@lang($attribute->label)"
            wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="{{ $attribute->dataName }}"
        @endisset
        {{ $attributes }}>
</x-leap::label>
