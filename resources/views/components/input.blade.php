@props(['attribute', 'placeholder'])

<x-leap::label>
    <input class="leap-input"
        @error($attribute->dataName) aria-errormessage="{{ $message }}" aria-invalid="true" @enderror
        placeholder="{{ $placeholder[$attribute->name] ?? $attribute->placeholder }}"
        @if (auth(config('leap.guard'))->user() && Gate::denies('leap::create') && Gate::denies('leap::update')) disabled @endif
        aria-label="@lang($attribute->label ?: $placeholder)"
        wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="{{ $attribute->dataName }}" {{ $attributes }}>
</x-leap::label>
