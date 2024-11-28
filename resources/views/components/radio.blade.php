@props(['attribute', 'name', 'label', 'placeholder'])

<x-leap::label></x-leap::label>

<fieldset class="leap-fieldset" @if ($attribute->options['group']) role="group" @endif>
    @foreach ($attribute->values as $value)
        <label class="leap-label">
            <span class="leap-label">{{ $value }}</span>
            <input class="leap-input" type="radio"
                @error($attribute->dataName ?? $name) aria-errormessage="{{ $message }}" aria-invalid="true" @enderror
                @if (auth(config('leap.guard'))->user() && Gate::denies('leap::create') && Gate::denies('leap::update')) disabled @endif
                wire:model{{ isset($attribute->wire) ? '.' . $attribute->wire : '' }}="{{ $attribute->dataName }}"
                value="{{ $value }}"
                {{ $attribute->inputAttributes() }}
                aria-label="{{ $value }}"
                {{ $attributes }}>
        </label>
    @endforeach
</fieldset>
