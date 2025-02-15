@props(['attribute', 'name', 'label', 'placeholder'])

<x-leap::label>
    <input class="leap-input"
        @error($attribute->dataName ?? $name) aria-errormessage="{{ $message }}" aria-invalid="true" @enderror
        @if ($attribute->disabled ?? false || (auth(config('leap.guard'))->user() && Gate::denies('leap::create') && Gate::denies('leap::update'))) disabled @endif
        @isset($attribute)
            placeholder="{{ $placeholder[$attribute->name] ?? $attribute->placeholder }}"
            wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="{{ $attribute->dataName }}"
            {{ $attribute->inputAttributes() }}
        @endisset
        aria-label="@lang($attribute->label ?? ($label ?? $name))"
        {{ $attributes }}>
</x-leap::label>
